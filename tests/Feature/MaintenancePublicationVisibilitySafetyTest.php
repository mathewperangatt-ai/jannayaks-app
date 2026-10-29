<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialRevisionRequest;
use App\Models\IntegrityIncident;
use App\Models\MediaItem;
use App\Models\Membership;
use App\Models\Profile;
use App\Models\ProfileIntegritySnapshot;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\EditorialContentVersioningService;
use App\Services\MembershipLifecycleService;
use App\Services\PostPublicationUpdateService;
use App\Services\ProfileAdministrationService;
use App\Services\ProfileIntegrityService;
use App\Services\ProfileUrlService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use ReflectionMethod;
use Tests\TestCase;

class MaintenancePublicationVisibilitySafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_normal_eligible_maintenance_publication_still_publishes_publicly(): void
    {
        $pack = $this->seedPublishedWithSnapshot();
        $admin = User::factory()->admin()->create();
        $member = $pack['member'];
        $application = $pack['application'];
        $maintenance = app(PostPublicationUpdateService::class);

        $maintenance->submit($application->fresh(), $member, 'Please update the board year to 2026.');
        $successorId = $this->prepareApprovedSuccessorId($application, $admin, 'Updated biography reflecting the 2026 board appointment.');
        $maintenance->releaseMaintenancePreview($application->fresh(), $admin);
        $maintenance->approveMaintenancePreview($application->fresh(), $member, $successorId);
        $maintenance->completePublication($application->fresh(), $admin);

        $profile = $pack['profile']->fresh();
        $this->assertSame('published', $profile->status);
        $this->assertNull($profile->unpublished_at);
        $this->assertTrue(app(ProfileUrlService::class)->isPubliclyVisible($profile));
        $this->assertSame($successorId, (int) $application->fresh()->published_english_editorial_content_id);
        $this->assertNotNull(
            ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('snapshot_hash')
        );
    }

    public function test_hidden_maintenance_publication_updates_baseline_so_restore_can_succeed(): void
    {
        $pack = $this->seedPublishedWithSnapshot();
        $admin = User::factory()->admin()->create();
        $member = $pack['member'];
        $application = $pack['application'];
        $administration = app(ProfileAdministrationService::class);
        $maintenance = app(PostPublicationUpdateService::class);

        $maintenance->submit($application->fresh(), $member, 'Please update the board year to 2026.');
        $successorId = $this->prepareApprovedSuccessorId($application, $admin, 'Hidden-update biography for 2026.');
        $maintenance->releaseMaintenancePreview($application->fresh(), $admin);
        $maintenance->approveMaintenancePreview($application->fresh(), $member, $successorId);

        $administration->temporarilyHideLivingProfile($application->fresh(), $admin, 'Security hold during update.');
        $maintenance->completePublication($application->fresh(), $admin);

        $profile = $pack['profile']->fresh();
        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($profile));
        $this->assertSame($successorId, (int) $application->fresh()->published_english_editorial_content_id);

        $assessment = app(ProfileIntegrityService::class)->assessPublicContentTrust($profile);
        $this->assertSame('match', $assessment['outcome']);
        $this->assertFalse($assessment['requires_integrity_waiver']);

        $administration->restoreLivingProfileVisibility(
            $application->fresh(),
            $admin,
            'Content update reviewed; restore public visibility.',
        );
        $this->assertTrue(app(ProfileUrlService::class)->isPubliclyVisible($profile->fresh()));
    }

    public function test_membership_past_grace_blocks_maintenance_publication(): void
    {
        $pack = $this->seedPublishedWithSnapshot();
        $admin = User::factory()->admin()->create();
        $member = $pack['member'];
        $application = $pack['application'];
        $maintenance = app(PostPublicationUpdateService::class);

        $maintenance->submit($application->fresh(), $member, 'Please update the board year to 2026.');
        $successorId = $this->prepareApprovedSuccessorId($application, $admin, 'Biography that must not publish while lapsed.');
        $maintenance->releaseMaintenancePreview($application->fresh(), $admin);
        $maintenance->approveMaintenancePreview($application->fresh(), $member, $successorId);

        $this->forceLapsedPastGrace($pack['profile']->fresh());

        try {
            $maintenance->completePublication($application->fresh(), $admin);
            $this->fail('Membership-lapsed profiles must not receive maintenance publication.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Membership lifecycle', $e->getMessage());
        }

        $this->assertNotSame(
            $successorId,
            (int) $application->fresh()->published_english_editorial_content_id
        );
    }

    public function test_pending_submission_does_not_alter_published_content(): void
    {
        $pack = $this->seedPublishedWithSnapshot();
        $before = (int) $pack['application']->published_english_editorial_content_id;

        app(PostPublicationUpdateService::class)->submit(
            $pack['application']->fresh(),
            $pack['member'],
            'Pending request only — must not move published pointers.',
        );

        $this->assertSame($before, (int) $pack['application']->fresh()->published_english_editorial_content_id);
        $this->assertSame(
            EditorialRevisionRequest::STATUS_SUBMITTED,
            EditorialRevisionRequest::query()->where('application_id', $pack['application']->id)->value('status')
        );
        $this->assertTrue(app(ProfileUrlService::class)->isPubliclyVisible($pack['profile']->fresh()));
    }

    public function test_hidden_editorial_publication_does_not_trust_changed_identity(): void
    {
        $pack = $this->seedPublishedWithSnapshot();
        $admin = User::factory()->admin()->create();
        $trustedName = (string) ProfileIntegritySnapshot::query()
            ->where('profile_id', $pack['profile']->id)
            ->value('display_name');

        $successorId = $this->prepareHiddenCustomerApprovedUpdate(
            $pack,
            $admin,
            'Editorial-only revision while identity was altered.'
        );

        $pack['profile']->forceFill(['display_name' => 'Unreviewed Identity Drift'])->save();

        app(PostPublicationUpdateService::class)->completePublication($pack['application']->fresh(), $admin);

        $profile = $pack['profile']->fresh();
        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($profile));
        $this->assertSame($successorId, (int) $pack['application']->fresh()->published_english_editorial_content_id);
        $this->assertSame(
            $trustedName,
            ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('display_name')
        );

        $assessment = app(ProfileIntegrityService::class)->assessPublicContentTrust($profile);
        $this->assertSame('mismatch', $assessment['outcome']);
        $this->assertContains('identity', $assessment['differing_sections']);
        $this->assertTrue($assessment['requires_integrity_waiver']);

        try {
            app(ProfileAdministrationService::class)->restoreLivingProfileVisibility(
                $pack['application']->fresh(),
                $admin,
                'Ordinary restore must fail after identity drift.',
            );
            $this->fail('Ordinary restore must refuse when identity drifted during hidden publication.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('trusted integrity snapshot', $e->getMessage());
        }

        $this->assertSame(0, StaffActionLog::query()->where('action', 'profile.visibility_restored')->count());
        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($profile->fresh()));
    }

    public function test_hidden_editorial_publication_does_not_trust_changed_bio_headline(): void
    {
        $pack = $this->seedPublishedWithSnapshot();
        $admin = User::factory()->admin()->create();
        $integrity = app(ProfileIntegrityService::class);

        $pack['profile']->forceFill(['bio_headline' => 'Trusted headline'])->save();
        $integrity->refreshSnapshot($pack['profile']->fresh(), 'test_trusted_headline');

        $successorId = $this->prepareHiddenCustomerApprovedUpdate(
            $pack,
            $admin,
            'Editorial-only revision while headline was altered.'
        );

        $pack['profile']->forceFill(['bio_headline' => 'Unreviewed headline drift'])->save();

        app(PostPublicationUpdateService::class)->completePublication($pack['application']->fresh(), $admin);

        $profile = $pack['profile']->fresh();
        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($profile));
        $this->assertSame($successorId, (int) $pack['application']->fresh()->published_english_editorial_content_id);
        $this->assertSame(
            'Trusted headline',
            ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('bio_headline')
        );

        $assessment = $integrity->assessPublicContentTrust($profile);
        $this->assertSame('mismatch', $assessment['outcome']);
        $this->assertContains('identity', $assessment['differing_sections']);
        $this->assertTrue($assessment['requires_integrity_waiver']);

        try {
            app(ProfileAdministrationService::class)->restoreLivingProfileVisibility(
                $pack['application']->fresh(),
                $admin,
                'Ordinary restore must fail after headline drift.',
            );
            $this->fail('Ordinary restore must refuse when bio_headline drifted during hidden publication.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('trusted integrity snapshot', $e->getMessage());
        }

        $this->assertSame(0, StaffActionLog::query()->where('action', 'profile.visibility_restored')->count());
        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($profile->fresh()));
    }

    public function test_hidden_editorial_publication_does_not_trust_changed_photograph_state(): void
    {
        $pack = $this->seedPublishedWithSnapshot();
        $admin = User::factory()->admin()->create();
        $successorId = $this->prepareHiddenCustomerApprovedUpdate(
            $pack,
            $admin,
            'Editorial-only revision while a photograph appeared.'
        );

        MediaItem::query()->create([
            'mediable_type' => (new Profile)->getMorphClass(),
            'mediable_id' => $pack['profile']->id,
            'media_type' => MediaItem::TYPE_PROFILE_PHOTO,
            'storage_path_key' => 'profile-media/tests/drift-portrait.jpg',
            'disk' => 'public',
            'privacy' => MediaItem::PRIVACY_PUBLIC,
            'review_status' => MediaItem::REVIEW_APPROVED,
            'is_primary' => true,
            'display_order' => 0,
            'uploaded_by_id' => $admin->id,
            'reviewed_by_user_id' => $admin->id,
            'reviewed_at' => now(),
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'photo_sha256' => str_repeat('ab', 32),
            'width' => 100,
            'height' => 100,
        ]);

        app(PostPublicationUpdateService::class)->completePublication($pack['application']->fresh(), $admin);

        $profile = $pack['profile']->fresh();
        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($profile));
        $this->assertSame($successorId, (int) $pack['application']->fresh()->published_english_editorial_content_id);
        $this->assertNull(
            ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('primary_photo_media_id')
        );

        $assessment = app(ProfileIntegrityService::class)->assessPublicContentTrust($profile);
        $this->assertSame('mismatch', $assessment['outcome']);
        $this->assertContains('photos', $assessment['differing_sections']);
        $this->assertTrue($assessment['requires_integrity_waiver']);
        $this->assertSame(0, StaffActionLog::query()->where('action', 'profile.visibility_restored')->count());
    }

    public function test_hidden_publication_leaves_open_incident_blocking_ordinary_restore(): void
    {
        $pack = $this->seedPublishedWithSnapshot();
        $admin = User::factory()->admin()->create();
        $successorId = $this->prepareHiddenCustomerApprovedUpdate(
            $pack,
            $admin,
            'Editorial revision with a pre-existing integrity incident.'
        );

        $incident = IntegrityIncident::query()->create([
            'profile_id' => $pack['profile']->id,
            'run_id' => 'hidden-pub-incident',
            'type' => IntegrityIncident::TYPE_IDENTITY,
            'mode' => 'report',
            'detection_reason' => 'identity mismatch before hidden publication',
            'expected_state' => ['display_name' => 'trusted'],
            'observed_state' => ['display_name' => 'observed'],
            'detection_fingerprint' => hash('sha256', 'hidden-pub-incident'),
            'detected_at' => now(),
            'status' => IntegrityIncident::STATUS_OPEN,
        ]);

        app(PostPublicationUpdateService::class)->completePublication($pack['application']->fresh(), $admin);

        $profile = $pack['profile']->fresh();
        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($profile));
        $this->assertSame($successorId, (int) $pack['application']->fresh()->published_english_editorial_content_id);
        $this->assertNull($incident->fresh()->resolved_at);
        $this->assertSame(IntegrityIncident::STATUS_OPEN, $incident->fresh()->status);

        $assessment = app(ProfileIntegrityService::class)->assessPublicContentTrust($profile);
        $this->assertTrue($assessment['requires_integrity_waiver']);
        $this->assertContains($incident->id, $assessment['blocking_incident_ids']);

        try {
            app(ProfileAdministrationService::class)->restoreLivingProfileVisibility(
                $pack['application']->fresh(),
                $admin,
                'Ordinary restore must remain blocked by the open incident.',
            );
            $this->fail('Open content-mismatch incidents must continue to block ordinary restoration.');
        } catch (InvalidArgumentException) {
            // expected
        }

        $this->assertSame(0, StaffActionLog::query()->where('action', 'profile.visibility_restored')->count());
        $this->assertNull($incident->fresh()->resolved_at);
    }

    public function test_exceptional_offline_publication_while_hidden_preserves_visibility_and_non_editorial_trust(): void
    {
        $pack = $this->seedPublishedWithSnapshot();
        $admin = User::factory()->admin()->create();
        $trustedName = (string) ProfileIntegritySnapshot::query()
            ->where('profile_id', $pack['profile']->id)
            ->value('display_name');

        app(ProfileAdministrationService::class)->temporarilyHideLivingProfile(
            $pack['application']->fresh(),
            $admin,
            'Security hold before exceptional publication.',
        );

        $published = EditorialContent::query()->findOrFail(
            $pack['application']->fresh()->published_english_editorial_content_id
        );
        $successor = app(EditorialContentVersioningService::class)->applyEditorUpdate($published, [
            'body' => 'Exceptional offline editorial revision while hidden.',
            'title' => (string) $published->title,
            'summary' => (string) $published->summary,
        ], $admin);
        $successor = app(EditorialContentVersioningService::class)->applyEditorUpdate($successor, [
            'status' => EditorialContent::STATUS_APPROVED,
        ], $admin);

        $pack['application']->forceFill([
            'customer_approved_english_editorial_content_id' => $successor->id,
            'customer_approved_at' => now(),
            'preview_english_editorial_content_id' => $successor->id,
        ])->save();
        $pack['profile']->forceFill(['display_name' => 'Exceptional Drift Name'])->save();

        app(ApplicationWorkflowService::class)->publish(
            $pack['application']->fresh(),
            $admin,
            false,
            'Authorised offline publication while the profile remains hidden.',
        );

        $profile = $pack['profile']->fresh();
        $this->assertSame('inactive', $profile->status);
        $this->assertNotNull($profile->unpublished_at);
        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($profile));
        $this->assertSame($successor->id, (int) $pack['application']->fresh()->published_english_editorial_content_id);
        $this->assertSame(
            $trustedName,
            ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('display_name')
        );

        $assessment = app(ProfileIntegrityService::class)->assessPublicContentTrust($profile);
        $this->assertSame('mismatch', $assessment['outcome']);
        $this->assertContains('identity', $assessment['differing_sections']);
    }

    public function test_inactive_without_unpublished_at_does_not_become_public_on_publication(): void
    {
        $pack = $this->seedPublishedWithSnapshot();
        $admin = User::factory()->admin()->create();
        $successorId = $this->prepareHiddenCustomerApprovedUpdate(
            $pack,
            $admin,
            'Revision against an inconsistent inactive visibility state.'
        );

        // Inconsistent shape: inactive but unpublished_at cleared.
        $pack['profile']->forceFill([
            'status' => 'inactive',
            'unpublished_at' => null,
        ])->save();

        app(PostPublicationUpdateService::class)->completePublication($pack['application']->fresh(), $admin);

        $profile = $pack['profile']->fresh();
        $this->assertSame($successorId, (int) $pack['application']->fresh()->published_english_editorial_content_id);
        $this->assertSame('inactive', $profile->status);
        $this->assertNotNull($profile->unpublished_at);
        $this->assertFalse(app(ProfileUrlService::class)->isPubliclyVisible($profile));
    }

    public function test_baseline_advancement_api_rejects_unrelated_callers_and_sections(): void
    {
        $pack = $this->seedPublishedWithSnapshot();
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $integrity = app(ProfileIntegrityService::class);

        $this->assertFalse(method_exists($integrity, 'recordAuthorizedContentBaseline'));

        try {
            $integrity->advanceTrustedBaselineForAuthorizedPublication(
                $pack['profile']->fresh(),
                $editor,
                'should-fail',
                ['editorial'],
            );
            $this->fail('Editors must not advance trusted baselines.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('administrator', strtolower($e->getMessage()));
        }

        try {
            $integrity->advanceTrustedBaselineForAuthorizedPublication(
                $pack['profile']->fresh(),
                $admin,
                'should-fail',
                ['identity', 'photos'],
            );
            $this->fail('Non-editorial sections must not be advanceable via hidden publication API.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('editorial', strtolower($e->getMessage()));
        }

        $persist = new ReflectionMethod(ProfileIntegrityService::class, 'persistCanonicalSnapshot');
        $this->assertTrue($persist->isPrivate());
    }

    /**
     * @return array{member: User, application: Application, profile: Profile}
     */
    private function seedPublishedWithSnapshot(): array
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $admin = User::factory()->admin()->create();

        $application = Application::factory()->historical('accomplished')->paid()->create([
            'user_id' => $member->id,
            'source_method' => 'admin_test_demo',
            'broad_location' => 'Kozhikode',
            'full_name' => 'Maintenance Safety Leader',
            'preferred_display_name' => 'Maintenance Safety Leader',
        ]);

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'member_approved',
            'full_name' => 'Maintenance Safety Leader',
            'display_name' => 'Maintenance Safety Leader',
            'profession' => 'Public Servant',
            'slug' => 'maintenance-safety-'.strtolower(bin2hex(random_bytes(3))),
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application->forceFill(['profile_id' => $profile->id])->save();

        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'Maintenance Safety Leader',
            'body' => 'Approved English biography for maintenance visibility safety.',
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

        return [
            'member' => $member,
            'application' => $published->fresh(),
            'profile' => Profile::query()->findOrFail($published->profile_id),
        ];
    }

    private function prepareApprovedSuccessorId(Application $application, User $admin, string $body): int
    {
        $publishedId = (int) $application->fresh()->published_english_editorial_content_id;
        $published = EditorialContent::query()->findOrFail($publishedId);

        $request = app(PostPublicationUpdateService::class)->prepareVersions($application->fresh(), $admin, [
            'body' => $body,
            'title' => (string) $published->title,
            'summary' => (string) $published->summary,
        ], null);

        $draft = EditorialContent::query()->findOrFail($request->resulting_english_editorial_content_id);
        $approved = app(EditorialContentVersioningService::class)->applyEditorUpdate($draft, [
            'status' => EditorialContent::STATUS_APPROVED,
        ], $admin);

        $request->forceFill([
            'resulting_english_editorial_content_id' => $approved->id,
        ])->save();

        return (int) $approved->id;
    }

    /**
     * @param  array{member: User, application: Application, profile: Profile}  $pack
     */
    private function prepareHiddenCustomerApprovedUpdate(array $pack, User $admin, string $body): int
    {
        $maintenance = app(PostPublicationUpdateService::class);
        $maintenance->submit($pack['application']->fresh(), $pack['member'], $body);
        $successorId = $this->prepareApprovedSuccessorId($pack['application'], $admin, $body);
        $maintenance->releaseMaintenancePreview($pack['application']->fresh(), $admin);
        $maintenance->approveMaintenancePreview($pack['application']->fresh(), $pack['member'], $successorId);
        app(ProfileAdministrationService::class)->temporarilyHideLivingProfile(
            $pack['application']->fresh(),
            $admin,
            'Security hold during maintenance publication.',
        );

        return $successorId;
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
