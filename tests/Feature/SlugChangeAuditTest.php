<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Profile;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\ProfileUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Authorized-change evidence: public URL (slug) changes and publication must
 * leave staff audit events with actor and before/after values.
 */
class SlugChangeAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_slug_selection_is_audited_with_old_and_new_values(): void
    {
        [$member, $application, $profile] = $this->seedApplicationWithSlug('A1B2C3');
        $service = app(ProfileUrlService::class);
        // Personal slugs must be name-derived; take a system-suggested valid candidate.
        $candidate = $service->suggestPersonalSlugs('Slug Audit Leader', (int) $profile->id)[0]['slug'];

        $updated = $service->selectPersonalSlug($profile->fresh(), $member, $candidate, 'accomplished');

        $this->assertSame($candidate, $updated->slug);

        $event = StaffActionLog::query()
            ->where('action', 'profile_url.slug_changed')
            ->where('subject_id', $profile->id)
            ->where('actor_user_id', $member->id)
            ->firstOrFail();

        $this->assertSame('A1B2C3', (string) $event->before['slug']);
        $this->assertSame($candidate, (string) $event->after['slug']);
        $this->assertNotNull($event->ip_address);
    }

    public function test_unchanged_slug_selection_does_not_duplicate_audit_event(): void
    {
        [$member, $application, $profile] = $this->seedApplicationWithSlug('A1B2C3');
        $service = app(ProfileUrlService::class);
        $candidate = $service->suggestPersonalSlugs('Slug Audit Leader', (int) $profile->id)[0]['slug'];

        $service->selectPersonalSlug($profile->fresh(), $member, $candidate, 'accomplished');
        $countAfterChange = StaffActionLog::query()->where('action', 'profile_url.slug_changed')->count();
        $this->assertSame(1, $countAfterChange);

        // Re-selecting the SAME slug takes the no-change early return: no new event.
        $service->selectPersonalSlug($profile->fresh(), $member, $candidate, 'accomplished');

        $this->assertSame(
            1,
            StaffActionLog::query()->where('action', 'profile_url.slug_changed')->count()
        );
    }

    public function test_publication_assigns_and_audits_initial_slug_and_publication_event(): void
    {
        [$application] = $this->seedPublishableApplication();
        $admin = User::factory()->admin()->create();

        $published = app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);
        $profile = $published->profile->fresh();

        $this->assertNotNull($profile->slug);

        $assigned = StaffActionLog::query()
            ->where('action', 'profile_url.slug_assigned')
            ->where('subject_id', $profile->id)
            ->where('actor_user_id', $admin->id)
            ->firstOrFail();
        $this->assertSame($profile->slug, (string) $assigned->after['slug']);

        // B5: existing publication audit events remain intact and unaudited-twice.
        $this->assertSame(
            1,
            StaffActionLog::query()
                ->where('action', 'application.published')
                ->where('subject_id', $application->id)
                ->count()
        );
    }

    /**
     * @return array{0: User, 1: Application, 2: Profile}
     */
    private function seedApplicationWithSlug(string $slug): array
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER, 'email_verified_at' => now()]);
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'package_tier' => 'accomplished',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
            'customer_approved_at' => null,
            'customer_approved_by_user_id' => null,
        ]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Slug Audit Leader',
            'display_name' => 'Slug Audit Leader',
            'profession' => 'Leader',
            'slug' => $slug,
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application->forceFill(['profile_id' => $profile->id])->save();

        return [$member, $application->fresh(), $profile->fresh()];
    }

    /**
     * @return array{0: Application, 1: Profile}
     */
    private function seedPublishableApplication(): array
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'package_tier' => 'accomplished',
            'source_method' => 'admin_test_demo',
            'status' => Application::STATUS_AWAITING_PUBLICATION,
        ]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'member_approved',
            'full_name' => 'Slug Publish Leader',
            'display_name' => 'Slug Publish Leader',
            'profession' => 'Leader',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application->forceFill(['profile_id' => $profile->id])->save();

        $english = \App\Models\EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => \App\Models\EditorialContent::LANGUAGE_EN,
            'status' => \App\Models\EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'Slug Publish Leader',
            'body' => 'Approved English biography for publication.',
            'summary' => 'Approved summary.',
            'source_material' => '',
            'ai_generated' => false,
        ]);

        $application->forceFill([
            'customer_approved_at' => now(),
            'customer_approved_english_editorial_content_id' => $english->id,
            'customer_approved_by_user_id' => $member->id,
            'status' => Application::STATUS_AWAITING_PUBLICATION,
        ])->save();

        return [$application->fresh(), $profile->fresh()];
    }
}
