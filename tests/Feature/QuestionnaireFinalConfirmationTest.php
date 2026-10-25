<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionnaireFinalConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guidance_and_confirmation_are_shown_and_submission_waits_for_the_checkbox(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $application = $this->interview($user);

        $page = $this->actingAs($user)->get(route('online-interview.show', $application));
        $page->assertOk()
            ->assertSee('Help us tell your story well', false)
            ->assertSee('you may leave it blank', false)
            ->assertSee('separate opportunity to review and approve your profile before publication', false)
            ->assertSee('നിങ്ങളുടെ കഥ നന്നായി പറയാൻ ഞങ്ങളെ സഹായിക്കുക', false)
            ->assertSee('I have reviewed my answers and am ready to submit my questionnaire for editorial preparation.', false)
            ->assertSee('എഡിറ്റോറിയൽ തയ്യാറെടുപ്പിനായി ചോദ്യാവലി സമർപ്പിക്കാൻ ഞാൻ തയ്യാറാണ്.', false)
            ->assertSee('Submit Questionnaire', false);

        $html = $page->getContent();
        $this->assertDoesNotMatchRegularExpression('/name="questionnaire_confirmed"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/id="submitQuestionnaire"[^>]*disabled/', $html);

        $this->actingAs($user)->patchJson(route('online-interview.save', $application), [
            'question_id' => 'q2',
            'answer' => 'A saved draft without confirmation.',
        ])->assertOk();
        $this->assertNull($application->fresh()->online_interview_completed_at);

        $this->actingAs($user)
            ->from(route('online-interview.show', $application))
            ->post(route('online-interview.submit', $application), [
                'answers' => [
                    'q1' => 'Location Check has worked in public health for twenty years.',
                    'q2' => 'A saved draft without confirmation.',
                ],
            ])
            ->assertRedirect(route('online-interview.show', $application))
            ->assertSessionHasErrors('questionnaire_confirmed');

        $application->refresh();
        $this->assertNull($application->online_interview_completed_at);
        $this->assertSame(Application::STATUS_INTERVIEW_IN_PROGRESS, $application->status);
        $this->assertDatabaseHas('interview_answers', [
            'application_id' => $application->id,
            'question_id' => 'q1',
            'original_answer' => 'Location Check has worked in public health for twenty years.',
        ]);

        $this->actingAs($user)->get(route('applications.payment', $application))
            ->assertRedirect(route('online-interview.show', $application));
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, EditorialContent::query()->count());
    }

    public function test_a_saved_draft_survives_a_week_and_payment_stays_closed_until_submission(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $application = $this->interview($user);

        $this->actingAs($user)->patch(route('online-interview.save', $application), [
            'answers' => [
                'q2' => 'This draft must still be here after a week.',
            ],
        ])->assertRedirect();

        $this->travel(8)->days();

        $this->actingAs($user)
            ->get(route('online-interview.show', $application))
            ->assertOk()
            ->assertSee('This draft must still be here after a week.', false)
            ->assertDontSee('SUBMITTED · READ-ONLY', false);

        $this->actingAs($other)
            ->get(route('online-interview.show', $application))
            ->assertRedirect();

        $application->refresh();
        $this->assertNull($application->online_interview_completed_at);
        $this->assertSame(Application::STATUS_INTERVIEW_IN_PROGRESS, $application->status);

        $this->actingAs($user)->get(route('applications.payment', $application))
            ->assertRedirect(route('online-interview.show', $application));
        $this->assertSame(0, Payment::query()->count());

        $this->actingAs($user)->postJson(route('online-interview.submit', $application), [
            'answers' => [
                'q1' => 'Location Check, still answering a week later.',
                'q2' => 'This draft must still be here after a week.',
            ],
            'questionnaire_confirmed' => '1',
        ])->assertOk()->assertJsonPath('next_step', 'payment');

        $application->refresh();
        $this->assertNotNull($application->online_interview_completed_at);
        $this->assertSame(0, EditorialContent::query()->count());
        $this->assertSame(0, Payment::query()->count());

        $this->travel(10)->days();
        $this->actingAs($user)
            ->get(route('online-interview.show', $application))
            ->assertOk()
            ->assertSee('SUBMITTED · READ-ONLY', false)
            ->assertSee('Location Check, still answering a week later.', false);
        $this->assertSame(0, Payment::query()->count());
    }

    private function interview(User $user): Application
    {
        $application = Application::factory()->create([
            'user_id' => $user->id,
            'package_tier' => 'distinguished',
            'source_method' => 'online_interview',
            'full_name' => 'Location Check',
            'payment_status' => Application::PAYMENT_STATUS_PENDING,
            'status' => Application::STATUS_INTERVIEW_IN_PROGRESS,
        ]);

        return $application->fresh() ?? $application;
    }
}
