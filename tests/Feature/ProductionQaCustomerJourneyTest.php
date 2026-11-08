<?php

namespace Tests\Feature;

use App\Http\Controllers\OnlineInterviewController;
use App\Models\Application;
use App\Models\MediaItem;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\User;
use App\Services\ApplicationPaymentStateService;
use App\Services\ProfileMediaService;
use App\Support\OnlineInterviewCatalog;
use App\Support\ProfileEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

/**
 * Corrections from the live production customer journey: Google on the
 * registration page, interview completion journey, waived-payment states,
 * dashboard stage messaging and photograph access after a waiver.
 */
class ProductionQaCustomerJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jannayaks.media.public_disk' => 'public']);
        Storage::fake('public');
        Storage::fake('private_uploads');
    }

    public function test_registration_page_offers_google_and_username_password(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Continue with Google', false)
            ->assertSee('href="'.route('auth.google').'"', false)
            ->assertSee('or create an account using username and password', false)
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false);
    }

    public function test_google_sign_up_creates_one_member_and_never_changes_an_existing_staff_role(): void
    {
        $this->mockGoogleUser('google-new-1', 'new.customer@example.com', 'New Customer');
        $this->get(route('auth.google.callback'))->assertRedirect(route('apply'));
        $this->assertSame(1, User::query()->where('email', 'new.customer@example.com')->count());
        $this->assertSame(User::ROLE_MEMBER, User::query()->where('email', 'new.customer@example.com')->value('role'));

        auth()->logout();
        $this->mockGoogleUser('google-new-1', 'new.customer@example.com', 'New Customer');
        $this->get(route('auth.google.callback'))->assertRedirect();
        $this->assertSame(1, User::query()->where('email', 'new.customer@example.com')->count());

        auth()->logout();
        $admin = User::factory()->admin()->create(['email' => 'staff@example.com', 'email_verified_at' => now()]);
        $this->mockGoogleUser('google-staff-1', 'staff@example.com', 'Staff Person');
        $this->get(route('auth.google.callback'))->assertRedirect();
        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
        $this->assertSame(1, User::query()->where('email', 'staff@example.com')->count());
    }

    public function test_questionnaire_keeps_22_questions_and_optional_q7_q8_under_entering_public_life(): void
    {
        $questions = collect(config('online_interview.questions'))->keyBy('id');
        $sections = array_keys((array) config('online_interview.sections'));

        $this->assertCount(22, $questions);
        $this->assertSame(['q1'], $questions->filter(fn ($q) => ! empty($q['required']))->keys()->all());
        $this->assertSame(2, array_search('entering_public_life', $sections, true), 'Entering Public Life is Part 3.');

        foreach (['q7', 'q8'] as $id) {
            $this->assertSame('entering_public_life', $questions[$id]['section']);
            $this->assertFalse($questions[$id]['required']);
            $this->assertNotSame('', trim((string) $questions[$id]['label_ml']));
        }
        $this->assertStringStartsWith('How did you first become involved in public, community', $questions['q7']['label_en']);
        $this->assertStringStartsWith('What were the main stages, roles or developments', $questions['q8']['label_en']);
    }

    public function test_html_submission_returns_to_dashboard_with_message_and_answers_stay_read_only(): void
    {
        [$member, $application] = $this->interviewReadyApplication();

        $response = $this->actingAs($member)->post(route('online-interview.submit', $application), [
            'questionnaire_confirmed' => '1',
            'accuracy_confirmed' => '1',
        ]);

        $response->assertRedirect(route('applications.show', $application))
            ->assertSessionHas('interview_submitted', OnlineInterviewController::SUBMITTED_MESSAGE);
        $this->assertNotNull($application->fresh()->online_interview_completed_at);

        $this->actingAs($member)->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('Your Online Interview has been submitted. Please wait for the Jannayaks editorial team to contact you.', false)
            ->assertSee('View submitted answers', false);

        $readOnly = $this->actingAs($member)->get(route('online-interview.show', $application))
            ->assertOk()
            ->assertSee('SUBMITTED · READ-ONLY', false)
            ->assertDontSee('Save and continue (partial)', false)
            ->getContent();
        $this->assertSame(2, substr_count($readOnly, 'href="'.route('applications.show', $application).'"'));
        $this->assertStringContainsString('disabled readonly', $readOnly);

        $completedAt = $application->fresh()->online_interview_completed_at;
        $this->actingAs($member)->post(route('online-interview.submit', $application), [
            'questionnaire_confirmed' => '1',
            'accuracy_confirmed' => '1',
        ])->assertRedirect(route('applications.show', $application));
        $this->assertEquals($completedAt, $application->fresh()->online_interview_completed_at);

        $this->actingAs($member)
            ->postJson(route('online-interview.save', $application), ['answers' => ['q1' => 'Changed after submission']])
            ->assertForbidden();
        $this->assertSame('Answer for q1', \App\Models\InterviewAnswer::query()
            ->where('application_id', $application->id)->where('question_id', 'q1')->value('original_answer'));
    }

    public function test_waived_payment_page_shows_waived_and_no_payment_prompt_in_both_languages(): void
    {
        [$member, $application] = $this->waivedApplication();

        foreach (['en' => 'Payment waived', 'ml' => 'പേയ്‌മെൻ്റ് ഒഴിവാക്കി'] as $lang => $label) {
            $this->actingAs($member)
                ->get(route('applications.payment', ['application' => $application, 'lang' => $lang]))
                ->assertOk()
                ->assertSee($label, false)
                ->assertDontSee('Not started', false)
                ->assertDontSee('ആരംഭിച്ചിട്ടില്ല', false)
                ->assertDontSee('Complete payment to proceed to editorial review.', false)
                ->assertDontSee('എഡിറ്റോറിയൽ അവലോകനത്തിന് പോകാൻ പേയ്‌മെൻ്റ് പൂർത്തിയാക്കുക.', false)
                ->assertDontSee('Pay Now', false)
                ->assertDontSee('ഇപ്പോൾ പേയ്‌മെൻ്റ് ചെയ്യുക', false)
                ->assertDontSee('pay-initiate-form', false)
                ->assertDontSee('App payment_status:', false)
                ->assertDontSee('Package Price Breakdown', false);
        }

        $this->actingAs($member)
            ->post(route('applications.payment.initiate', $application))
            ->assertRedirect();
        $this->actingAs($member)
            ->postJson(route('applications.payment.initiate', $application))
            ->assertStatus(409);

        $payments = Payment::query()->where('application_id', $application->id)->get();
        $this->assertCount(1, $payments, 'Only the existing zero-amount waiver ledger row; no gateway attempt.');
        $this->assertSame(Payment::STATUS_WAIVED, $payments->first()->status);
        $this->assertSame('0.00', (string) $payments->first()->amount);
        $this->assertNull($payments->first()->invoice_number);
        $this->assertNull($payments->first()->tax_invoice_number);
        $this->assertNull($payments->first()->razorpay_link_url);
    }

    public function test_dashboard_reflects_waiver_and_editorial_stage_without_payment_prompts(): void
    {
        [$member, $application] = $this->waivedApplication();
        $application->forceFill([
            'source_method' => 'online_interview',
            'online_interview_completed_at' => now(),
        ])->save();

        $stages = [
            Application::STATUS_AWAITING_EDITORIAL_REVIEW => 'Profile preparation is underway.',
            Application::STATUS_IN_EDITORIAL_REVIEW => 'Editorial review is underway.',
            Application::STATUS_EDITORIAL_APPROVED => 'Waiting for Jannayaks to release your profile preview.',
        ];

        foreach ($stages as $status => $text) {
            $application->forceFill(['status' => $status])->save();

            $this->actingAs($member)->get(route('applications.show', $application))
                ->assertOk()
                ->assertSee('Your Online Interview has been submitted. Please wait for the Jannayaks editorial team to contact you.', false)
                ->assertSee($text, false)
                ->assertSee('Payment waived', false)
                ->assertDontSee('Pay to unlock interview', false)
                ->assertDontSee('Complete payment to unlock', false)
                ->assertDontSee('Review your profile', false);
        }
    }

    public function test_waived_customer_can_upload_photographs_within_the_existing_limit_for_admin_review(): void
    {
        [$member, $application] = $this->waivedApplication();
        $limit = ProfileEntitlements::photoSlots($application);

        $this->actingAs($member)->get(route('applications.media', $application))
            ->assertOk()
            ->assertSee('0 of '.$limit.' photographs used', false);

        for ($i = 1; $i <= $limit; $i++) {
            $this->actingAs($member)->post(route('applications.media.store', $application), [
                'photo' => UploadedFile::fake()->image('portrait-'.$i.'.jpg', 400, 500),
            ])->assertRedirect(route('applications.media', $application));
        }

        $this->actingAs($member)->post(route('applications.media.store', $application), [
            'photo' => UploadedFile::fake()->image('one-too-many.jpg', 400, 500),
        ])->assertRedirect();
        $this->actingAs($member)->post(route('applications.media.store', $application), [
            'photo' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $photos = MediaItem::query()->where('mediable_id', $application->profile_id)->get();
        $this->assertCount($limit, $photos);
        $this->assertTrue($photos->every(fn (MediaItem $m) => $m->review_status === MediaItem::REVIEW_PENDING));
        $this->assertSame(1, $photos->where('is_primary', true)->count());

        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $primary = $photos->firstWhere('is_primary', true);
        $approved = app(ProfileMediaService::class)->approve($primary, $admin);
        $this->assertSame(MediaItem::REVIEW_APPROVED, $approved->review_status);
        $this->assertTrue((bool) $approved->is_primary);
    }

    /**
     * @return array{0: User, 1: Application}
     */
    private function waivedApplication(): array
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Waived Customer',
            'display_name' => 'Waived Customer',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application = Application::factory()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'broad_location' => 'Kozhikode',
            'source_method' => 'direct_submission',
            'payment_status' => Application::PAYMENT_STATUS_PENDING,
            'package_tier' => 'distinguished',
        ]);

        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        app(ApplicationPaymentStateService::class)->recordStaffWaiver($application, $admin, 'Production QA waiver.');

        return [$member, $application->fresh()];
    }

    /**
     * @return array{0: User, 1: Application}
     */
    private function interviewReadyApplication(): array
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->for($member)->paid()->create([
            'package_tier' => 'distinguished',
            'source_method' => 'online_interview',
        ]);

        $answers = [];
        foreach (OnlineInterviewCatalog::idsForTier('distinguished') as $qid) {
            $answers[$qid] = 'Answer for '.$qid;
        }
        $this->actingAs($member)->patchJson(route('online-interview.save', $application), ['answers' => $answers])->assertOk();

        return [$member, $application->fresh()];
    }

    private function mockGoogleUser(string $id, string $email, string $name): void
    {
        $social = Mockery::mock(SocialiteUser::class);
        $social->shouldReceive('getId')->andReturn($id);
        $social->shouldReceive('getEmail')->andReturn($email);
        $social->shouldReceive('getName')->andReturn($name);

        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->andReturn($social);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
