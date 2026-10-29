<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\IntegrityIncident;
use App\Models\MediaItem;
use App\Models\Membership;
use App\Models\Profile;
use App\Models\ProfileIntegritySnapshot;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\MembershipLifecycleService;
use App\Services\ProfileAdministrationService;
use App\Services\ProfileMediaService;
use App\Services\ProfileUrlService;
use App\Services\PublicProfileSearchService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class ProfileVisibilityAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_temporarily_hide_profile_without_deleting_content(): void
    {
        $pack = $this->seedPublishedProfile(withPhoto: true);
        $admin = User::factory()->admin()->create();
        $profile = $pack['profile']->fresh();
        $slug = (string) $profile->slug;
        $snapshotId = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('id');
        $mediaId = $pack['photo']->id;
        $englishId = EditorialContent::query()->where('profile_id', $profile->id)->value('id');

        app(ProfileAdministrationService::class)->temporarilyHideLivingProfile(
            $pack['application']->fresh(),
            $admin,
            'Security review of public biography.',
        );

        $hidden = $profile->fresh();
        $this->assertSame('inactive', $hidden->status);
        $this->assertNotNull($hidden->unpublished_at);
        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($hidden));
        $this->get(route('profiles.public', $slug))->assertNotFound();
        $this->assertFalse(
            app(PublicProfileSearchService::class)->publishedBaseQuery()->whereKey($hidden->id)->exists()
        );

        $this->assertSame($snapshotId, ProfileIntegritySnapshot::query()->where('profile_id', $hidden->id)->value('id'));
        $this->assertTrue(MediaItem::query()->whereKey($mediaId)->exists());
        $this->assertTrue(EditorialContent::query()->whereKey($englishId)->exists());
        $this->assertSame(0, IntegrityIncident::query()->count());

        $audit = StaffActionLog::query()->where('action', 'profile.temporarily_hidden')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_user_id);
        $this->assertSame('temporary', $audit->after['hide_kind'] ?? null);
        $this->assertSame('Security review of public biography.', $audit->after['reason'] ?? null);

        $summary = app(ProfileAdministrationService::class)->visibilityAdminSummary($hidden);
        $this->assertFalse($summary['public']);
        $this->assertSame('Temporarily hidden', $summary['label']);
    }

    public function test_repeated_temporary_hide_does_not_duplicate_audit(): void
    {
        $pack = $this->seedPublishedProfile();
        $admin = User::factory()->admin()->create();
        $service = app(ProfileAdministrationService::class);

        $service->temporarilyHideLivingProfile($pack['application']->fresh(), $admin, 'First hide.');
        $service->temporarilyHideLivingProfile($pack['application']->fresh(), $admin, 'Second hide must not audit again.');

        $this->assertSame(1, StaffActionLog::query()->where('action', 'profile.temporarily_hidden')->count());
    }

    public function test_editor_cannot_hide_or_restore_visibility(): void
    {
        $pack = $this->seedPublishedProfile();
        $editor = User::factory()->editor()->create();
        $service = app(ProfileAdministrationService::class);

        try {
            $service->temporarilyHideLivingProfile($pack['application']->fresh(), $editor, 'Editor attempt.');
            $this->fail('Editors must not temporarily hide profiles.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('administrator', $e->getMessage());
        }

        $admin = User::factory()->admin()->create();
        $service->temporarilyHideLivingProfile($pack['application']->fresh(), $admin, 'Admin hide.');

        try {
            $service->restoreLivingProfileVisibility($pack['application']->fresh(), $editor, 'Editor restore.');
            $this->fail('Editors must not restore visibility.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('administrator', $e->getMessage());
        }

        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($pack['profile']->fresh()));
    }

    public function test_admin_can_restore_visibility_after_temporary_hide(): void
    {
        $pack = $this->seedPublishedProfile();
        $admin = User::factory()->admin()->create();
        $service = app(ProfileAdministrationService::class);
        $slug = (string) $pack['profile']->slug;
        $englishId = (int) $pack['application']->published_english_editorial_content_id;
        $snapshotHash = ProfileIntegritySnapshot::query()->where('profile_id', $pack['profile']->id)->value('snapshot_hash');

        $service->temporarilyHideLivingProfile($pack['application']->fresh(), $admin, 'Hold for review.');
        $service->restoreLivingProfileVisibility($pack['application']->fresh(), $admin, 'Reviewed — content unchanged.');

        $restored = $pack['profile']->fresh();
        $this->assertSame('published', $restored->status);
        $this->assertNull($restored->unpublished_at);
        $this->assertTrue(app(ProfileUrlService::class)->isPubliclyVisible($restored));
        $this->get(route('profiles.public', $slug))->assertOk();
        $this->assertSame($englishId, (int) $pack['application']->fresh()->published_english_editorial_content_id);
        $this->assertSame(
            $snapshotHash,
            ProfileIntegritySnapshot::query()->where('profile_id', $restored->id)->value('snapshot_hash')
        );

        $audit = StaffActionLog::query()->where('action', 'profile.visibility_restored')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_user_id);
        $this->assertSame('Reviewed — content unchanged.', $audit->after['reason'] ?? null);
        $this->assertSame('match', $audit->after['integrity_verification_outcome'] ?? null);
        $this->assertFalse((bool) ($audit->after['integrity_waiver_used'] ?? true));
    }

    public function test_restore_refuses_when_canonical_content_mismatches_snapshot(): void
    {
        $pack = $this->seedPublishedProfile();
        $admin = User::factory()->admin()->create();
        $service = app(ProfileAdministrationService::class);
        $service->temporarilyHideLivingProfile($pack['application']->fresh(), $admin, 'Hold.');

        $pack['profile']->forceFill(['display_name' => 'Suspicious Altered Name'])->save();

        try {
            $service->restoreLivingProfileVisibility(
                $pack['application']->fresh(),
                $admin,
                'Ordinary restore must fail.',
            );
            $this->fail('Mismatched content must block ordinary restoration.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('does not match the trusted integrity snapshot', $e->getMessage());
        }

        $hidden = $pack['profile']->fresh();
        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($hidden));
        $this->assertSame(0, StaffActionLog::query()->where('action', 'profile.visibility_restored')->count());
    }

    public function test_restore_refuses_when_trusted_snapshot_is_missing(): void
    {
        $pack = $this->seedPublishedProfile();
        $admin = User::factory()->admin()->create();
        $service = app(ProfileAdministrationService::class);
        $service->temporarilyHideLivingProfile($pack['application']->fresh(), $admin, 'Hold.');

        ProfileIntegritySnapshot::query()->where('profile_id', $pack['profile']->id)->delete();

        try {
            $service->restoreLivingProfileVisibility(
                $pack['application']->fresh(),
                $admin,
                'Ordinary restore must fail.',
            );
            $this->fail('Missing snapshot must block ordinary restoration.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('no trusted integrity snapshot', $e->getMessage());
        }

        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($pack['profile']->fresh()));
    }

    public function test_restore_refuses_when_open_content_mismatch_incident_exists(): void
    {
        $pack = $this->seedPublishedProfile();
        $admin = User::factory()->admin()->create();
        $service = app(ProfileAdministrationService::class);
        $service->temporarilyHideLivingProfile($pack['application']->fresh(), $admin, 'Hold.');

        IntegrityIncident::query()->create([
            'profile_id' => $pack['profile']->id,
            'run_id' => 'test-run-1',
            'type' => IntegrityIncident::TYPE_EDITORIAL,
            'mode' => 'report',
            'detection_reason' => 'editorial body hash mismatch',
            'expected_state' => ['english_content_hash' => 'aaa'],
            'observed_state' => ['english_content_hash' => 'bbb'],
            'detection_fingerprint' => hash('sha256', 'editorial-test-fingerprint'),
            'detected_at' => now(),
            'status' => IntegrityIncident::STATUS_OPEN,
        ]);

        try {
            $service->restoreLivingProfileVisibility(
                $pack['application']->fresh(),
                $admin,
                'Ordinary restore must fail.',
            );
            $this->fail('Open content-mismatch incidents must block ordinary restoration.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('integrity', strtolower($e->getMessage()));
        }

        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($pack['profile']->fresh()));
        $this->assertSame(1, IntegrityIncident::query()->whereNull('resolved_at')->count());
    }

    public function test_integrity_waiver_requires_separate_reason_and_is_audited(): void
    {
        $pack = $this->seedPublishedProfile();
        $admin = User::factory()->admin()->create();
        $service = app(ProfileAdministrationService::class);
        $service->temporarilyHideLivingProfile($pack['application']->fresh(), $admin, 'Hold.');
        $pack['profile']->forceFill(['display_name' => 'Altered Without Review'])->save();

        try {
            $service->restoreLivingProfileVisibility(
                $pack['application']->fresh(),
                $admin,
                'Restore after review.',
                '   ',
            );
            $this->fail('Blank integrity waiver must not override a content mismatch.');
        } catch (InvalidArgumentException) {
            // expected
        }

        $incident = IntegrityIncident::query()->create([
            'profile_id' => $pack['profile']->id,
            'run_id' => 'test-run-2',
            'type' => IntegrityIncident::TYPE_IDENTITY,
            'mode' => 'report',
            'detection_reason' => 'display_name mismatch',
            'expected_state' => ['display_name' => 'Visibility Control Leader'],
            'observed_state' => ['display_name' => 'Altered Without Review'],
            'detection_fingerprint' => hash('sha256', 'identity-test-fingerprint'),
            'detected_at' => now(),
            'status' => IntegrityIncident::STATUS_OPEN,
        ]);

        $service->restoreLivingProfileVisibility(
            $pack['application']->fresh(),
            $admin,
            'Restore after manual content review.',
            'Founder reviewed identity change; temporary public restore authorised.',
        );

        $this->assertTrue(app(ProfileUrlService::class)->isPubliclyVisible($pack['profile']->fresh()));
        $audit = StaffActionLog::query()->where('action', 'profile.visibility_restored')->firstOrFail();
        $this->assertSame('Restore after manual content review.', $audit->after['reason'] ?? null);
        $this->assertTrue((bool) ($audit->after['integrity_waiver_used'] ?? false));
        $this->assertSame(
            'Founder reviewed identity change; temporary public restore authorised.',
            $audit->after['integrity_waiver_reason'] ?? null
        );
        $this->assertContains($incident->id, $audit->after['integrity_blocking_incident_ids'] ?? []);
        $this->assertNull($incident->fresh()->resolved_at);
        $this->assertSame(IntegrityIncident::STATUS_OPEN, $incident->fresh()->status);
    }

    public function test_integrity_waiver_cannot_bypass_erasure_or_membership(): void
    {
        $pack = $this->seedPublishedProfile();
        $admin = User::factory()->admin()->create();
        $service = app(ProfileAdministrationService::class);
        $service->temporarilyHideLivingProfile($pack['application']->fresh(), $admin, 'Hold.');
        ProfileIntegritySnapshot::query()->where('profile_id', $pack['profile']->id)->delete();

        $pack['profile']->forceFill(['erasure_completed_at' => now()])->save();
        try {
            $service->restoreLivingProfileVisibility(
                $pack['application']->fresh(),
                $admin,
                'Restore reason',
                'Waiver cannot bypass erasure.',
            );
            $this->fail('Waiver must not bypass erasure.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('erased', strtolower($e->getMessage()));
        }

        $pack['profile']->forceFill(['erasure_completed_at' => null])->save();
        $this->forceLapsedPastGrace($pack['profile']->fresh());
        try {
            $service->restoreLivingProfileVisibility(
                $pack['application']->fresh(),
                $admin,
                'Restore reason',
                'Waiver cannot bypass membership.',
            );
            $this->fail('Waiver must not bypass membership lifecycle.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Membership lifecycle', $e->getMessage());
        }

        $this->assertSame(0, StaffActionLog::query()->where('action', 'profile.visibility_restored')->count());
    }

    public function test_restore_after_official_unpublish_also_works(): void
    {
        $pack = $this->seedPublishedProfile();
        $admin = User::factory()->admin()->create();
        $service = app(ProfileAdministrationService::class);

        $service->unpublishLivingProfile($pack['application']->fresh(), $admin, 'Official notice received.');
        $this->assertSame(1, StaffActionLog::query()->where('action', 'profile.living_unpublished')->count());
        $this->assertSame('official_unpublish', StaffActionLog::query()
            ->where('action', 'profile.living_unpublished')
            ->value('after')['hide_kind'] ?? null);

        $service->restoreLivingProfileVisibility($pack['application']->fresh(), $admin, 'Notice withdrawn after review.');
        $this->assertTrue(app(ProfileUrlService::class)->isPubliclyVisible($pack['profile']->fresh()));
    }

    public function test_restore_refuses_erased_and_suspended_profiles(): void
    {
        $pack = $this->seedPublishedProfile();
        $admin = User::factory()->admin()->create();
        $service = app(ProfileAdministrationService::class);
        $service->temporarilyHideLivingProfile($pack['application']->fresh(), $admin, 'Hold.');

        $pack['profile']->forceFill(['erasure_completed_at' => now()])->save();
        try {
            $service->restoreLivingProfileVisibility($pack['application']->fresh(), $admin, 'Should fail.');
            $this->fail('Erased profiles must not be restored.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('erased', strtolower($e->getMessage()));
        }

        $pack['profile']->forceFill([
            'erasure_completed_at' => null,
            'suspended_at' => now(),
            'status' => 'suspended',
        ])->save();
        try {
            $service->restoreLivingProfileVisibility($pack['application']->fresh(), $admin, 'Should fail.');
            $this->fail('Suspended profiles must not be restored.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('suspended', strtolower($e->getMessage()));
        }
    }

    public function test_restore_refuses_when_membership_is_past_grace(): void
    {
        $pack = $this->seedPublishedProfile();
        $admin = User::factory()->admin()->create();
        $profile = $pack['profile']->fresh();
        $this->assertInstanceOf(Membership::class, $profile->membership);

        $this->forceLapsedPastGrace($profile);
        $profile = $profile->fresh();
        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($profile));
        $this->assertFalse(app(ProfileAdministrationService::class)->canRestoreLivingProfileVisibility($profile));

        try {
            app(ProfileAdministrationService::class)->restoreLivingProfileVisibility(
                $pack['application']->fresh(),
                $admin,
                'Must not bypass membership lapse.',
            );
            $this->fail('Membership-lapsed profiles must not be restored via admin visibility restore.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Membership lifecycle', $e->getMessage());
        }

        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($profile->fresh()));
        $this->assertSame(0, StaffActionLog::query()->where('action', 'profile.visibility_restored')->count());
    }

    public function test_existing_official_unpublish_still_hides_without_memorial(): void
    {
        $pack = $this->seedPublishedProfile();
        $admin = User::factory()->admin()->create();
        $slug = (string) $pack['profile']->slug;
        $name = $pack['profile']->full_name;

        app(ProfileAdministrationService::class)->unpublishLivingProfile(
            $pack['application']->fresh(),
            $admin,
            'Official notice and proof received.',
        );

        $profile = $pack['profile']->fresh();
        $this->assertSame('inactive', $profile->status);
        $this->assertSame($name, $profile->full_name);
        $this->assertSame($slug, $profile->slug);
        $this->get(route('profiles.public', $slug))->assertNotFound();
        $this->assertSame(1, StaffActionLog::query()->where('action', 'profile.living_unpublished')->count());
    }

    /**
     * @return array{member: User, application: Application, profile: Profile, photo?: MediaItem}
     */
    private function seedPublishedProfile(bool $withPhoto = false): array
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $editor = User::factory()->editor()->create();
        $reviewer = User::factory()->editor()->create();
        $admin = User::factory()->admin()->create();

        $application = Application::factory()->historical('accomplished')->paid()->create([
            'user_id' => $member->id,
            'source_method' => 'admin_test_demo',
            'broad_location' => 'Kozhikode',
            'full_name' => 'Visibility Control Leader',
            'preferred_display_name' => 'Visibility Control Leader',
        ]);

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'member_approved',
            'full_name' => 'Visibility Control Leader',
            'display_name' => 'Visibility Control Leader',
            'profession' => 'Public Servant',
            'slug' => 'visibility-leader-'.strtolower(bin2hex(random_bytes(3))),
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application->forceFill(['profile_id' => $profile->id])->save();

        $pack = [
            'member' => $member,
            'application' => $application->fresh(),
            'profile' => $profile->fresh(),
        ];

        if ($withPhoto) {
            $photo = app(ProfileMediaService::class)->uploadProfilePhoto(
                $profile,
                UploadedFile::fake()->image('portrait.jpg', 400, 500),
                $editor,
            );
            app(ProfileMediaService::class)->approve($photo, $reviewer);
            $pack['photo'] = $photo->fresh();
        }

        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'Visibility Control Leader',
            'body' => 'Approved English biography for visibility controls.',
            'summary' => 'Approved summary.',
            'source_material' => '',
            'ai_generated' => false,
        ]);

        $application->fresh()->forceFill([
            'customer_approved_at' => now(),
            'customer_approved_english_editorial_content_id' => $english->id,
            'customer_approved_by_user_id' => $member->id,
            'status' => Application::STATUS_AWAITING_PUBLICATION,
        ])->save();

        $published = app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);
        $pack['application'] = $published->fresh();
        $pack['profile'] = Profile::query()->findOrFail($published->profile_id);

        return $pack;
    }

    private function forceLapsedPastGrace(Profile $profile): void
    {
        $membership = $profile->membership;
        $this->assertInstanceOf(Membership::class, $membership);

        $ends = Carbon::parse('2026-01-01', config('app.timezone'))->startOfDay();
        $membership->forceFill([
            'starts_on' => $ends->copy()->subYear()->toDateString(),
            'ends_on' => $ends->toDateString(),
            'renewal_due_on' => $ends->toDateString(),
            'retention_until' => $ends->copy()->addYear()->toDateString(),
            'status' => 'active',
        ])->save();

        Carbon::setTestNow($ends->copy()->addDays(5));
        app(MembershipLifecycleService::class)->processMembershipLifecycle($membership->id, $ends->copy()->addDays(5));
    }
}
