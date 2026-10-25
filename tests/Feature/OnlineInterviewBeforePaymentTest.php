<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class OnlineInterviewBeforePaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function intake(array $overrides = []): array
    {
        return array_merge([
            'source_method' => 'online_interview',
            'full_name' => 'Sree Narayanan',
            'contact_email' => 'sree.narayanan@example.test',
        ], $overrides);
    }

    public function test_opening_the_form_and_choosing_online_interview_does_not_create_a_payment(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get(route('apply', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Continue to Online Interview →', false)
            ->assertSee('₹4,000.00', false)
            ->assertSee('₹1,800.00', false)
            ->assertDontSee('After you sign in, payment comes next.', false)
            ->assertDontSee('Continue to payment →', false);

        $this->assertSame(0, Application::query()->count());
        $this->assertSame(0, Payment::query()->count());

        $created = $this->actingAs($user)->postJson(route('applications.store'), $this->intake());
        $created->assertCreated();
        $application = Application::query()->where('user_id', $user->id)->sole();

        $created->assertJsonPath('redirect_to', route('online-interview.show', $application));
        $this->assertSame(Application::STATUS_INTERVIEW_IN_PROGRESS, $application->status);
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $application->payment_status);
        $this->assertSame(0, Payment::query()->count());

        $this->actingAs($user)
            ->postJson(route('applications.payment.initiate', $application))
            ->assertForbidden();
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_questionnaire_can_be_saved_resumed_and_submitted_before_payment(): void
    {
        Config::set('services.razorpay.enabled', false);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->postJson(route('applications.store'), $this->intake())->assertCreated();
        $application = Application::query()->where('user_id', $user->id)->sole();

        $this->actingAs($user)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('Continue Online Interview', false)
            ->assertDontSee('Pay to unlock interview', false)
            ->assertDontSee('Complete payment to unlock the Online Interview', false);

        $this->actingAs($user)->patchJson(route('online-interview.save', $application), [
            'question_id' => 'q2',
            'answer' => 'A draft that must survive a failed submit.',
        ])->assertOk();

        $this->actingAs($user)->postJson(route('online-interview.submit', $application), [
            'answers' => ['q2' => 'A draft that must survive a failed submit.'],
        ])->assertStatus(422);

        $this->assertNull($application->fresh()->online_interview_completed_at);
        $this->assertSame(0, Payment::query()->count());

        $this->actingAs($user)
            ->getJson(route('online-interview.show', $application))
            ->assertOk()
            ->assertJsonPath('answers.q2', 'A draft that must survive a failed submit.');

        $submitted = $this->actingAs($user)->postJson(route('online-interview.submit', $application), [
            'answers' => [
                'q1' => 'Sree Narayanan, told in the customer’s own words.',
                'q2' => 'A draft that must survive a failed submit.',
            ],
            'questionnaire_confirmed' => '1',
        ]);
        $submitted->assertOk()->assertJsonPath('next_step', 'payment');

        $application->refresh();
        $this->assertNotNull($application->online_interview_completed_at);
        $this->assertSame(Application::STATUS_INTERVIEW_SUBMITTED, $application->status);
        $this->assertSame(0, EditorialContent::query()->count());
        $this->assertSame(0, Payment::query()->count());

        $again = $this->actingAs($user)->postJson(route('online-interview.submit', $application), [
            'answers' => ['q1' => 'This retry must not replace the submitted answer.'],
        ]);
        $again->assertOk()->assertJsonPath('already_submitted', true);
        $this->assertSame(1, Application::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, EditorialContent::query()->count());
        $this->assertDatabaseHas('interview_answers', [
            'application_id' => $application->id,
            'question_id' => 'q1',
            'original_answer' => 'Sree Narayanan, told in the customer’s own words.',
        ]);

        $this->actingAs($user)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('Payment is the next step.', false)
            ->assertSee('Continue to payment', false);

        $this->actingAs($user)
            ->get(route('applications.payment', $application))
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('applications.payment.initiate', $application))
            ->assertCreated();

        $payment = Payment::query()->where('application_id', $application->id)->sole();
        $this->assertSame(472000, $payment->totalPaise());
        $this->assertSame(Payment::STATUS_INITIATED, $payment->status);
        $this->assertFalse($payment->isPaidOrBetter());
    }

    public function test_repeated_create_clicks_do_not_duplicate_the_application(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $first = $this->actingAs($user)->postJson(route('applications.store'), $this->intake())->assertCreated();
        $second = $this->actingAs($user)->postJson(route('applications.store'), $this->intake());

        $second->assertStatus(409)->assertJsonPath('application_id', $first->json('application_id'));
        $this->assertSame(1, Application::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_direct_submission_goes_to_documents_and_not_the_interview(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $created = $this->actingAs($user)->postJson(route('applications.store'), $this->intake([
            'source_method' => 'direct_submission',
            'full_name' => 'Direct Document Customer',
            'contact_email' => 'direct.documents@example.test',
        ]))->assertCreated();

        $application = Application::query()->where('user_id', $user->id)->sole();
        $created->assertJsonPath('redirect_to', route('applications.upload.show', $application));
        $this->assertSame(Application::STATUS_INTAKE_IN_PROGRESS, $application->status);
        $this->assertSame(0, Payment::query()->count());

        $this->actingAs($user)
            ->get(route('applications.upload.show', $application))
            ->assertOk()
            ->assertSee('Upload source material', false);

        $this->actingAs($user)
            ->getJson(route('online-interview.show', $application))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('Upload source material', false)
            ->assertDontSee('Continue Online Interview', false);
    }

    public function test_optional_url_hint_is_empty_or_name_derived(): void
    {
        $invalid = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($invalid)->get(route('apply', ['lang' => 'en']))->assertOk();
        $this->actingAs($invalid)->postJson(route('applications.store'), $this->intake([
            'preferred_slug' => 'celebrity',
            'contact_email' => 'bad.hint@example.test',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('preferred_slug');
        $this->assertSame(0, Application::query()->where('user_id', $invalid->id)->count());

        $emptyUser = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($emptyUser)->postJson(route('applications.store'), $this->intake([
            'preferred_slug' => '',
            'full_name' => 'Empty Hint Person',
            'contact_email' => 'empty.hint@example.test',
        ]))->assertCreated();
        $empty = Application::query()->where('user_id', $emptyUser->id)->sole();
        $this->assertNull($empty->preferred_slug);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $hint = $this->actingAs($user)->postJson(route('applications.store'), $this->intake([
            'preferred_slug' => 'Sree',
            'contact_email' => 'sree.hint@example.test',
        ]))->assertCreated();

        $application = Application::query()->findOrFail($hint->json('application_id'));
        $this->assertSame('sree', $application->preferred_slug);
        $this->assertSame(0, Payment::query()->where('application_id', $application->id)->count());
    }
}
