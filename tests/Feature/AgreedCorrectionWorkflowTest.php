<?php

namespace Tests\Feature;

use App\Contracts\EditorialAiClient;
use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialCustomerApproval;
use App\Models\InMemoriamProfile;
use App\Models\InterviewAnswer;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\SlugRedirect;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\Ai\FakeEditorialAiClient;
use App\Services\ApplicationLocationService;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\EditorialGenerationService;
use App\Services\ProfileAdministrationService;
use App\Services\ProfileUrlService;
use App\Services\RefundService;
use App\Support\OnlineInterviewCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class AgreedCorrectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_open_application_per_email_allows_a_different_email(): void
    {
        $publishedOwner = User::factory()->create(['email' => 'published.person@example.com']);
        Application::factory()->create([
            'user_id' => $publishedOwner->id,
            'applicant_email_normalized' => 'published.person@example.com',
            'status' => Application::STATUS_PUBLISHED,
            'full_name' => 'Published Person',
        ]);

        $other = User::factory()->create(['email' => 'Other.Person@Example.com']);
        $this->actingAs($other)->postJson(route('applications.store'), [
            'source_method' => 'online_interview',
            'full_name' => 'Other Person',
            'broad_location' => 'Kozhikode',
            'contact_email' => 'other.person@example.com',
        ])->assertCreated();

        $created = Application::query()->where('user_id', $other->id)->firstOrFail();
        $this->assertSame('other.person@example.com', $created->applicant_email_normalized);
        $this->assertSame('Kozhikode', $created->broad_location);
        $this->assertSame(Application::STATUS_INTERVIEW_IN_PROGRESS, $created->status);
        $this->actingAs($other)->postJson(route('applications.store'), [
            'source_method' => 'online_interview',
            'full_name' => 'Other Person Again',
            'broad_location' => 'Kozhikode',
            'contact_email' => 'other.person@example.com',
        ])->assertStatus(409);

        $created->forceFill(['status' => Application::STATUS_CANCELLED])->save();
        $this->actingAs($other)->postJson(route('applications.store'), [
            'source_method' => 'online_interview',
            'full_name' => 'Other Person Restarted',
            'broad_location' => 'Kannur',
            'contact_email' => 'other.person@example.com',
        ])->assertCreated();
        $this->assertSame(2, Application::query()->where('user_id', $other->id)->count());
    }

    public function test_database_rejects_two_open_applications_for_the_same_email(): void
    {
        $index = DB::select("SELECT indexdef FROM pg_indexes WHERE indexname = 'applications_one_open_lifecycle_email'");
        $this->assertNotEmpty($index);
        $this->assertStringContainsString('UNIQUE', strtoupper((string) $index[0]->indexdef));

        Application::factory()->create([
            'applicant_email_normalized' => 'same.person@example.com',
            'status' => Application::STATUS_INTERVIEW_IN_PROGRESS,
        ]);

        try {
            DB::transaction(function (): void {
                Application::factory()->create([
                    'applicant_email_normalized' => 'same.person@example.com',
                    'status' => Application::STATUS_PUBLISHED,
                ]);
            });
            $this->fail('Two open applications for one email must be rejected.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', (string) ($exception->errorInfo[0] ?? ''));
        }

        Application::factory()->create([
            'applicant_email_normalized' => 'same.person@example.com',
            'status' => Application::STATUS_REFUNDED,
        ]);
        $this->assertSame(2, Application::query()->where('applicant_email_normalized', 'same.person@example.com')->count());
    }

    public function test_location_is_required_and_copied_without_replacing_existing_geography(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('applications.store'), [
            'source_method' => 'online_interview',
            'full_name' => 'No Location',
            'contact_email' => 'nolocation@example.com',
        ])->assertStatus(422)->assertJsonValidationErrors('broad_location');

        $application = Application::factory()->create([
            'user_id' => $user->id,
            'broad_location' => 'Kozhikode',
            'full_name' => 'Located Person',
        ]);
        $profile = app(ProfileUrlService::class)->ensureDraftProfileForApplication($application);
        $this->assertSame('Kozhikode', $profile->geography->locality_place);

        $profile->geography->forceFill(['locality_place' => 'Kannur'])->save();
        $application->forceFill(['broad_location' => 'Kollam'])->save();
        app(ApplicationLocationService::class)->copyBroadLocationIfEmpty($application->fresh(), $profile->fresh());
        $this->assertSame('Kannur', $profile->geography->fresh()->locality_place);
        $this->assertSame('Kollam', $application->fresh()->broad_location);
    }

    public function test_questionnaire_confirmation_is_stored_with_the_submitted_answers(): void
    {
        $user = User::factory()->create();
        $application = Application::factory()->create([
            'user_id' => $user->id,
            'source_method' => 'online_interview',
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_INTERVIEW_IN_PROGRESS,
            'full_name' => 'Interview Person',
        ]);

        $this->actingAs($user)->postJson(route('applications.payment.initiate', $application))
            ->assertForbidden();

        $this->actingAs($user)->patchJson(route('online-interview.save', $application), [
            'question_id' => 'q1',
            'answer' => 'I served in Kozhikode.',
        ])->assertOk();

        $this->actingAs($user)->postJson(route('online-interview.submit', $application))
            ->assertStatus(422)
            ->assertJsonValidationErrors('questionnaire_confirmed');
        $this->assertNull($application->fresh()->questionnaire_confirmed_at);

        $this->actingAs($user)->postJson(route('online-interview.submit', $application), [
            'questionnaire_confirmed' => true,
        ])->assertOk();

        $application->refresh();
        $this->assertNotNull($application->questionnaire_confirmed_at);
        $this->assertSame(OnlineInterviewCatalog::VERSION, $application->questionnaire_version);
        $this->assertSame(
            hash('sha256', (string) json_encode(['q1' => 'I served in Kozhikode.'], JSON_UNESCAPED_UNICODE)),
            $application->questionnaire_submission_sha256,
        );
        $this->assertSame(Application::STATUS_INTERVIEW_SUBMITTED, $application->status);
    }

    public function test_refund_eligibility_ends_at_the_first_customer_preview_and_does_not_reopen(): void
    {
        Config::set('jannayaks.ai.provider', 'fake');
        Config::set('jannayaks.ai.malayalam_pipeline_enabled', true);
        $this->app->instance(EditorialAiClient::class, new FakeEditorialAiClient);

        $editor = User::factory()->editor()->create();
        $member = User::factory()->create();
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'full_name' => 'Refund Milestone',
            'package_tier' => 'accomplished',
            'source_method' => 'online_interview',
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);
        InterviewAnswer::query()->create([
            'application_id' => $application->id,
            'user_id' => $member->id,
            'question_id' => 'q1',
            'original_answer' => 'A public life in Kozhikode.',
            'answered_at' => now(),
        ]);

        $payment = Payment::query()->where('application_id', $application->id)->firstOrFail();
        $refunds = app(RefundService::class);
        $this->assertTrue($refunds->isEligible($payment));
        $this->assertSame(60, $refunds->refundBasisConfigPercent());

        app(EditorialGenerationService::class)->generateForApplication($application->fresh(), $editor);
        $application = $application->fresh();
        $this->assertNotNull($application->profile_id);
        $this->assertTrue($refunds->isEligible($payment->fresh()));

        $english = EditorialContent::query()
            ->where('profile_id', $application->profile_id)
            ->where('language', EditorialContent::LANGUAGE_EN)
            ->firstOrFail();
        $english->forceFill([
            'status' => EditorialContent::STATUS_APPROVED,
            'reviewed_by_id' => $editor->id,
        ])->save();

        $workflow = app(CustomerEditorialWorkflowService::class);
        $released = $workflow->releaseForCustomerPreview($application->fresh(), $editor);
        $this->assertNotNull($released->first_customer_preview_at);
        $this->assertFalse($refunds->isEligible($payment->fresh()));

        $workflow->invalidateApprovalForEditorialContent($english->fresh(), $editor, 'Material English revision.');
        $released->refresh();
        $this->assertNull($released->customer_preview_released_at);
        $this->assertNotNull($released->first_customer_preview_at);
        $this->assertFalse($refunds->isEligible($payment->fresh()));
    }

    public function test_one_customer_approval_covers_english_and_malayalam_and_can_be_invalidated(): void
    {
        Config::set('jannayaks.ai.provider', 'fake');
        Config::set('jannayaks.ai.malayalam_pipeline_enabled', true);
        $this->app->instance(EditorialAiClient::class, new FakeEditorialAiClient);

        $editor = User::factory()->editor()->create();
        $member = User::factory()->create();
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'full_name' => 'Bilingual Consent',
            'package_tier' => 'accomplished',
            'source_method' => 'online_interview',
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);
        InterviewAnswer::query()->create([
            'application_id' => $application->id,
            'user_id' => $member->id,
            'question_id' => 'q1',
            'original_answer' => 'Consent covers both languages.',
            'answered_at' => now(),
        ]);
        app(EditorialGenerationService::class)->generateForApplication($application->fresh(), $editor);
        $application = $application->fresh();
        $english = EditorialContent::query()
            ->where('profile_id', $application->profile_id)
            ->where('language', EditorialContent::LANGUAGE_EN)
            ->firstOrFail();
        $malayalam = EditorialContent::query()
            ->where('profile_id', $application->profile_id)
            ->where('language', EditorialContent::LANGUAGE_ML)
            ->firstOrFail();
        $english->forceFill(['status' => EditorialContent::STATUS_APPROVED, 'reviewed_by_id' => $editor->id])->save();
        $malayalam->forceFill(['status' => EditorialContent::STATUS_APPROVED, 'reviewed_by_id' => $editor->id])->save();

        $workflow = app(CustomerEditorialWorkflowService::class);
        $released = $workflow->releaseForCustomerPreview($application->fresh(), $editor);
        $approval = $workflow->approvePreview($released, $member, $english->id);

        $this->assertSame(EditorialCustomerApproval::SCOPE_ENGLISH_AND_MALAYALAM, $approval->consent_scope);
        $this->assertSame($english->id, (int) $approval->english_editorial_content_id);
        $this->assertSame($malayalam->id, (int) $approval->malayalam_editorial_content_id);
        $this->assertSame(1, EditorialCustomerApproval::query()->where('application_id', $application->id)->count());

        try {
            $workflow->approvePreview($released->fresh(), $member, $english->id);
            $this->fail('Approving the same version again must not create a second consent.');
        } catch (InvalidArgumentException) {
            $this->assertSame(1, EditorialCustomerApproval::query()->where('application_id', $application->id)->whereNull('invalidated_at')->count());
        }
        $this->assertSame(1, EditorialCustomerApproval::query()->where('application_id', $application->id)->count());
    }

    public function test_material_change_invalidates_the_bilingual_consent(): void
    {
        Config::set('jannayaks.ai.provider', 'fake');
        Config::set('jannayaks.ai.malayalam_pipeline_enabled', true);
        $this->app->instance(EditorialAiClient::class, new FakeEditorialAiClient);

        $editor = User::factory()->editor()->create();
        $member = User::factory()->create();
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'full_name' => 'Consent Invalidation',
            'package_tier' => 'accomplished',
            'source_method' => 'online_interview',
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);
        InterviewAnswer::query()->create([
            'application_id' => $application->id,
            'user_id' => $member->id,
            'question_id' => 'q1',
            'original_answer' => 'Both languages were approved.',
            'answered_at' => now(),
        ]);
        app(EditorialGenerationService::class)->generateForApplication($application->fresh(), $editor);
        $application = $application->fresh();
        $english = EditorialContent::query()->where('profile_id', $application->profile_id)->where('language', EditorialContent::LANGUAGE_EN)->firstOrFail();
        $malayalam = EditorialContent::query()->where('profile_id', $application->profile_id)->where('language', EditorialContent::LANGUAGE_ML)->firstOrFail();
        $english->forceFill(['status' => EditorialContent::STATUS_APPROVED, 'reviewed_by_id' => $editor->id])->save();
        $malayalam->forceFill(['status' => EditorialContent::STATUS_APPROVED, 'reviewed_by_id' => $editor->id])->save();

        $workflow = app(CustomerEditorialWorkflowService::class);
        $released = $workflow->releaseForCustomerPreview($application->fresh(), $editor);
        $approval = $workflow->approvePreview($released, $member, $english->id);
        $this->assertNull($approval->invalidated_at);

        $workflow->invalidateApprovalForEditorialContent($malayalam->fresh(), $editor, 'Malayalam text changed.');
        $this->assertNotNull($approval->fresh()->invalidated_at);
    }

    public function test_profile_hides_the_biography_heading_and_places_appreciation_before_contact(): void
    {
        $member = User::factory()->create();
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'published',
            'published_at' => now(),
            'full_name' => 'Presentation Leader',
            'display_name' => 'Presentation Leader',
            'slug' => 'presentation.leader',
            'profession' => 'Teacher',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'Presentation Leader',
            'body' => 'A biography paragraph for the public profile.',
            'summary' => 'Summary.',
            'source_material' => '',
            'ai_generated' => false,
        ]);
        Application::factory()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'status' => Application::STATUS_PUBLISHED,
            'full_name' => 'Presentation Leader',
            'published_english_editorial_content_id' => $english->id,
        ]);

        $html = $this->get('/presentation.leader')->assertOk()->getContent();
        $this->assertStringContainsString('id="biography"', $html);
        $this->assertStringContainsString('class="jk-sr">Biography</h2>', $html);
        $this->assertDoesNotMatchRegularExpression('/<h2(?![^>]*\bjk-sr\b)[^>]*>\s*Biography\s*<\/h2>/', $html);
        $this->assertLessThan(strpos($html, 'id="wellwishers"'), strpos($html, 'id="biography"'));
        $this->assertLessThan(strpos($html, '<h2>Contact</h2>'), strpos($html, '<h2>Show appreciation</h2>'));
        $this->assertStringContainsString('object-fit:cover', $html);
    }

    public function test_administrators_can_change_email_transfer_url_and_convert_with_an_audit(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $profile = Profile::query()->create([
            'user_id' => $owner->id,
            'status' => 'published',
            'published_at' => now(),
            'full_name' => 'Meera Nair',
            'display_name' => 'Meera Nair',
            'slug' => 'meera.nair',
            'profession' => 'Teacher',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application = Application::factory()->paid()->create([
            'user_id' => $owner->id,
            'profile_id' => $profile->id,
            'applicant_email_normalized' => 'owner@example.com',
            'status' => Application::STATUS_PUBLISHED,
            'full_name' => 'Meera Nair',
        ]);
        $service = app(ProfileAdministrationService::class);

        try {
            $service->changeApplicantEmail($application, $editor, 'new-owner@example.com', 'Support request');
            $this->fail('An editor must not change the account email.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('administrator', strtolower($exception->getMessage()));
        }

        $changed = $service->changeApplicantEmail($application, $admin, 'New.Owner@Example.com', 'Customer asked for a new email.');
        $this->assertSame($owner->id, (int) $changed->user_id);
        $this->assertSame('new.owner@example.com', $owner->fresh()->email);
        $this->assertSame('new.owner@example.com', $changed->applicant_email_normalized);
        $this->assertTrue(StaffActionLog::query()->where('action', 'application.email_changed')->where('actor_user_id', $admin->id)->exists());

        $destination = User::factory()->create(['email' => 'destination@example.com']);
        $transferred = $service->transferToAccount($changed, $admin, $destination, 'Customer moved accounts.');
        $this->assertSame($destination->id, (int) $transferred->user_id);
        $this->assertSame($destination->id, (int) $profile->fresh()->user_id);
        $this->assertSame(1, Application::query()->where('profile_id', $profile->id)->count());
        $this->assertSame(1, Profile::query()->whereKey($profile->id)->count());

        $blocked = User::factory()->create(['email' => 'blocked@example.com']);
        Application::factory()->create([
            'user_id' => $blocked->id,
            'applicant_email_normalized' => 'blocked@example.com',
            'status' => Application::STATUS_PAYMENT_PENDING,
        ]);
        try {
            $service->transferToAccount($transferred->fresh(), $admin, $blocked, 'Should be refused.');
            $this->fail('A destination with an open application must be refused.');
        } catch (InvalidArgumentException) {
            $this->assertSame($destination->id, (int) $transferred->fresh()->user_id);
        }

        Profile::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'draft',
            'full_name' => 'Meera Nair',
            'display_name' => 'Meera Nair',
            'slug' => 'meeranair',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        try {
            $service->changeProfileUrl($transferred->fresh(), $admin, 'meeranair', 'Collision check.');
            $this->fail('A URL already in use must be refused.');
        } catch (InvalidArgumentException) {
            $this->assertSame('meera.nair', $profile->fresh()->slug);
        }

        $service->changeProfileUrl($transferred->fresh(), $admin, 'meera', 'Customer requested a clearer URL.');
        $this->assertSame('meera', $profile->fresh()->slug);
        $this->assertTrue(SlugRedirect::query()->where('old_slug', 'meera.nair')->where('new_slug', 'meera')->exists());
        $this->assertTrue(StaffActionLog::query()->where('action', 'profile.url_changed')->exists());

        $paymentCount = Payment::query()->where('application_id', $application->id)->count();
        $memorial = $service->convertLivingProfileToMemorial(
            $transferred->fresh(),
            $admin,
            'Ravi Nair',
            '9847000111',
            'Family requested a memorial.',
        );
        $again = $service->convertLivingProfileToMemorial(
            $transferred->fresh(),
            $admin,
            'Ravi Nair',
            '9847000111',
            'Repeated request must not create another memorial.',
        );

        $this->assertSame($memorial->id, $again->id);
        $this->assertSame($profile->id, (int) $memorial->living_profile_id);
        $this->assertSame('late.meera.nair', $memorial->fresh()->slug);
        $this->assertSame('inactive', $profile->fresh()->status);
        $this->assertNotNull($profile->fresh()->unpublished_at);
        $this->assertNull($profile->fresh()->slug);
        $this->assertSame($paymentCount, Payment::query()->where('application_id', $application->id)->count());
        $this->assertTrue(StaffActionLog::query()->where('action', 'profile.converted_to_memorial')->exists());
        $this->get('/meera')->assertNotFound();

        $memorial->forceFill([
            'status' => InMemoriamProfile::STATUS_PUBLISHED_ARCHIVED,
            'published_at' => now(),
            'hosting_starts_on' => now()->toDateString(),
            'hosting_ends_on' => now()->addYear()->toDateString(),
        ])->save();
        $this->get('/meera')->assertRedirect('/in-memoriam/late.meera.nair');
    }

    public function test_recommend_someone_still_opens_a_visitor_email_draft(): void
    {
        $html = $this->get(route('recommend.show'))->assertOk()->getContent();
        $this->assertStringContainsString("window.location.href = 'mailto:'", $html);
        $this->post(route('recommend.show'))->assertStatus(405);
    }
}
