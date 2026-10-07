<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialRevisionRequest;
use App\Models\Profile;
use App\Models\User;
use App\Services\CustomerEditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pass 1 — post-publication profile maintenance foundation.
 *
 * One complimentary bundled update per 3-month cycle anchored to the
 * profile's publication date (never moved by prior usage); requests outside
 * an unused window are classified as paid-update opportunities.
 */
class PostPublicationUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-01 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_published_customer_can_submit_a_post_publication_update_request(): void
    {
        [$member, $application, $profile] = $this->publishedProfile('2026-05-01');

        $response = $this->actingAs($member)
            ->from('/applications/'.$application->id.'/maintenance')
            ->post(route('applications.maintenance.store', $application), [
                'request_text' => 'Please update my designation to Chairman of XYZ Foundation and correct my hometown spelling.',
            ]);

        $response->assertRedirect(route('applications.show', $application));
        $response->assertSessionHas('maintenance_status', function (string $status) {
            return str_contains($status, 'Update request submitted')
                && str_contains($status, 'Complimentary update');
        });

        $request = EditorialRevisionRequest::query()->where('application_id', $application->id)->sole();
        $this->assertSame(EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE, $request->request_type);
        $this->assertSame(EditorialRevisionRequest::BILLING_COMPLIMENTARY, $request->billing_classification);
        $this->assertSame(EditorialRevisionRequest::STATUS_SUBMITTED, $request->status);
        $this->assertSame('2026-05-01', $request->eligibility_published_on->toDateString());
        $this->assertSame('2026-08-01', $request->next_eligible_on->toDateString());
        $this->assertSame($member->id, (int) $request->requested_by_user_id);
    }

    public function test_pre_publication_customer_cannot_use_the_post_publication_path(): void
    {
        [$member, $application] = $this->prePublicationProfile();

        $this->actingAs($member)
            ->get(route('applications.maintenance.show', $application))
            ->assertNotFound();

        $this->actingAs($member)
            ->post(route('applications.maintenance.store', $application), [
                'request_text' => 'Please change everything about my profile.',
            ])
            ->assertNotFound();

        $this->assertSame(0, EditorialRevisionRequest::query()->where('application_id', $application->id)->count());
    }

    public function test_request_is_classified_complimentary_when_currently_eligible(): void
    {
        [$member, $application] = $this->publishedProfile('2026-06-01');

        $this->actingAs($member)->post(route('applications.maintenance.store', $application), [
            'request_text' => 'Add my 2026 appointment as Chair of the town library trust.',
        ])->assertRedirect();

        $this->assertSame(
            EditorialRevisionRequest::BILLING_COMPLIMENTARY,
            EditorialRevisionRequest::query()->sole()->billing_classification,
        );
    }

    public function test_request_is_classified_paid_when_the_cycle_was_already_used(): void
    {
        [$member, $application] = $this->publishedProfile('2026-01-01');

        $used = EditorialRevisionRequest::query()->create([
            'application_id' => $application->id,
            'profile_id' => $application->profile_id,
            'requested_by_user_id' => $member->id,
            'round_number' => null,
            'request_type' => EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE,
            'billing_classification' => EditorialRevisionRequest::BILLING_COMPLIMENTARY,
            'status' => EditorialRevisionRequest::STATUS_COMPLETED,
            'request_text' => 'First complimentary update of the January cycle.',
        ]);
        EditorialRevisionRequest::query()->whereKey($used->id)->update(['created_at' => '2026-07-05 09:00:00']);

        $this->actingAs($member)->post(route('applications.maintenance.store', $application), [
            'request_text' => 'A second change within the same cycle should be classified as paid.',
        ])->assertRedirect();

        $request = EditorialRevisionRequest::query()
            ->where('request_type', EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE)
            ->where('status', EditorialRevisionRequest::STATUS_SUBMITTED)
            ->sole();
        $this->assertSame(EditorialRevisionRequest::BILLING_PAID, $request->billing_classification);
        $this->assertSame('2026-10-01', $request->next_eligible_on->toDateString());
    }

    public function test_eligibility_follows_the_publication_date_not_the_calendar(): void
    {
        // Published 5 months before "today" (2026-07-01): current cycle opened
        // at publication + 3 months (2026-04-01), so the update is complimentary.
        [$member, $application] = $this->publishedProfile('2026-02-01');

        $this->actingAs($member)->post(route('applications.maintenance.store', $application), [
            'request_text' => 'Correct my professional designation in the biography.',
        ])->assertRedirect();

        $request = EditorialRevisionRequest::query()->sole();
        $this->assertSame(EditorialRevisionRequest::BILLING_COMPLIMENTARY, $request->billing_classification);
        $this->assertSame('2026-02-01', $request->eligibility_published_on->toDateString());
        $this->assertSame('2026-08-01', $request->next_eligible_on->toDateString());
    }

    public function test_eligibility_recurs_every_three_months_from_publication_date(): void
    {
        [$member, $application] = $this->publishedProfile('2026-01-01');

        // Cycle 1 (January) used — but the anchor never moves: the April and
        // July windows still open on schedule.
        $used = EditorialRevisionRequest::query()->create([
            'application_id' => $application->id,
            'profile_id' => $application->profile_id,
            'requested_by_user_id' => $member->id,
            'round_number' => null,
            'request_type' => EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE,
            'billing_classification' => EditorialRevisionRequest::BILLING_COMPLIMENTARY,
            'status' => EditorialRevisionRequest::STATUS_COMPLETED,
            'request_text' => 'January cycle update.',
        ]);
        EditorialRevisionRequest::query()->whereKey($used->id)->update(['created_at' => '2026-01-05 09:00:00']);

        // Still inside the July window (opened 2026-07-01): complimentary again.
        $this->actingAs($member)->post(route('applications.maintenance.store', $application), [
            'request_text' => 'A new update in the July window must be complimentary again.',
        ])->assertRedirect();

        $this->assertSame(
            EditorialRevisionRequest::BILLING_COMPLIMENTARY,
            EditorialRevisionRequest::query()
                ->where('status', EditorialRevisionRequest::STATUS_SUBMITTED)
                ->sole()->billing_classification,
        );
    }

    public function test_a_bundled_request_with_multiple_changes_is_one_request(): void
    {
        [$member, $application] = $this->publishedProfile('2026-06-01');

        $this->actingAs($member)->post(route('applications.maintenance.store', $application), [
            'request_text' => 'Please make three changes together: correct the 2019 date, add the new designation, remove the outdated award.',
        ])->assertRedirect();

        $this->assertSame(1, EditorialRevisionRequest::query()->where('application_id', $application->id)->count());
    }

    public function test_photograph_replacement_stays_inside_the_request_and_the_existing_media_lane(): void
    {
        [$member, $application] = $this->publishedProfile('2026-06-01');
        $mediaBefore = DB::table('media_items')->count();

        $this->actingAs($member)->post(route('applications.maintenance.store', $application), [
            'request_text' => 'Please replace my photograph with a newer portrait and update my role to General Manager.',
        ])->assertRedirect();

        $this->assertSame(1, EditorialRevisionRequest::query()->where('application_id', $application->id)->count());
        $this->assertSame($mediaBefore, DB::table('media_items')->count());
        $this->assertTrue(str_contains(
            EditorialRevisionRequest::query()->sole()->request_text,
            'replace my photograph',
        ));
    }

    public function test_submission_does_not_edit_or_publish_editorial_content(): void
    {
        [$member, $application, $profile] = $this->publishedProfile('2026-06-01');
        $publishedEn = (int) $application->published_english_editorial_content_id;
        $contentsBefore = EditorialContent::query()->count();

        $this->actingAs($member)->post(route('applications.maintenance.store', $application), [
            'request_text' => 'Change requested through the maintenance lane only.',
        ])->assertRedirect();

        $application->refresh();
        $this->assertSame(Application::STATUS_PUBLISHED, $application->status);
        $this->assertSame($publishedEn, (int) $application->published_english_editorial_content_id);
        $this->assertSame($contentsBefore, EditorialContent::query()->count());
        $this->assertSame('published', $profile->fresh()->status);
    }

    public function test_customer_cannot_request_maintenance_for_another_customers_profile(): void
    {
        [$member, $application] = $this->publishedProfile('2026-06-01');
        $other = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($other)
            ->post(route('applications.maintenance.store', $application), [
                'request_text' => 'Trying to change someone else\'s profile.',
            ])
            ->assertForbidden();

        $this->assertSame(0, EditorialRevisionRequest::query()->where('application_id', $application->id)->count());
    }

    public function test_pending_attention_surfaces_the_maintenance_request(): void
    {
        [$member, $application] = $this->publishedProfile('2026-06-01');

        $this->actingAs($member)->post(route('applications.maintenance.store', $application), [
            'request_text' => 'Please add my new role as District Convenor.',
        ])->assertRedirect();

        Livewire::test(\App\Filament\Widgets\PendingAttentionWidget::class)
            ->assertSee('Published-profile update request — Complimentary')
            ->assertSee($application->full_name);
    }

    public function test_pre_publication_revision_flow_is_unchanged(): void
    {
        [$member, $application] = $this->prePublicationProfile();

        $request = app(CustomerEditorialWorkflowService::class)->requestRevision(
            $application->fresh(),
            $member,
            'Please soften the second paragraph before release.',
            EditorialRevisionRequest::TYPE_REVISION,
        );

        $this->assertSame(EditorialRevisionRequest::TYPE_REVISION, $request->request_type);
        $this->assertSame(1, $request->round_number);
        $this->assertNull($request->billing_classification);
        $this->assertSame(Application::STATUS_EDITORIAL_REVISION_REQUESTED, $application->fresh()->status);
    }

    /** @return array{0: User, 1: Application, 2: Profile} */
    private function publishedProfile(string $publishedAt): array
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'published',
            'full_name' => 'Arun Kumar Nair',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $profile->forceFill([
            'slug' => 'arun.kumar.nair',
            'published_at' => Carbon::parse($publishedAt)->startOfDay(),
            'slug_generated_at' => now(),
        ])->save();

        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => 'en',
            'version_number' => 1,
            'status' => 'approved',
            'title' => 'Arun Kumar Nair',
            'summary' => 'Summary.',
            'body' => 'Body text.',
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'package_tier' => 'distinguished',
            'source_method' => 'admin_test_demo',
        ]);
        // paid() is an afterCreating hook that resets status — reapply the
        // published state afterwards.
        $application->forceFill([
            'status' => Application::STATUS_PUBLISHED,
            'published_english_editorial_content_id' => $english->id,
        ])->save();

        return [$member, $application, $profile];
    }

    /** @return array{0: User, 1: Application} */
    private function prePublicationProfile(): array
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'customer_preview',
            'full_name' => 'Pre Publication Member',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => 'en',
            'version_number' => 1,
            'status' => 'approved',
            'title' => 'Pre Publication Member',
            'summary' => 'Summary.',
            'body' => 'Body text.',
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'package_tier' => 'distinguished',
            'source_method' => 'admin_test_demo',
        ]);
        $application->forceFill([
            'status' => Application::STATUS_EDITORIAL_APPROVED,
            'customer_preview_released_at' => now(),
            'preview_english_editorial_content_id' => $english->id,
        ])->save();

        return [$member, $application];
    }
}
