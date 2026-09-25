<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\IntegrityIncident;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\ProfileExternalLink;
use App\Models\ProfileIntegritySnapshot;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\ExternalVideoLinkService;
use App\Services\ProfileIntegrityService;
use App\Services\ProfileMediaService;
use App\Services\ProfileUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Published Profile Integrity Monitor — REPORT-ONLY (Wave 2C).
 */
class ProfileIntegrityMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['jannayaks.integrity.mode' => 'report']);
        config(['jannayaks.integrity.deep_verify_percent' => 100]);
    }

    /* A. Published profile gets a snapshot. */
    public function test_published_profile_gets_snapshot(): void
    {
        $profile = $this->seedPublishedProfile()['profile'];

        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertSame('application.published', (string) $snapshot->source_event);
    }

    /* B. Unpublished profiles get no snapshot; watchdog does not create one. */
    public function test_unpublished_profile_gets_no_snapshot(): void
    {
        [$member, $application, $profile] = $this->seedPublishableApplication();

        $this->assertNull(
            app(ProfileIntegrityService::class)->refreshSnapshot($profile, 'test')
        );

        Artisan::call('jannayaks:verify-profile-integrity');

        $this->assertSame(0, ProfileIntegritySnapshot::query()->count());
        $this->assertSame(0, IntegrityIncident::query()->count());
    }

    /* C. Snapshot identity state. */
    public function test_snapshot_contains_public_identity_state(): void
    {
        $profile = $this->seedPublishedProfile()['profile'];

        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame(strtolower((string) $profile->slug), (string) $snapshot->slug);
        $this->assertSame($profile->display_name, (string) $snapshot->display_name);
        $this->assertSame($profile->profession, (string) $snapshot->profession);
    }

    /* D. Snapshot pinned editorial IDs and content hashes. */
    public function test_snapshot_contains_pinned_editorial_ids_and_hashes(): void
    {
        $pack = $this->seedPublishedProfile();
        $application = $pack['application']->fresh();
        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $pack['profile']->id)->firstOrFail();

        $this->assertSame((int) $application->published_english_editorial_content_id, (int) $snapshot->english_editorial_content_id);

        $english = EditorialContent::query()->findOrFail($application->published_english_editorial_content_id);
        // Mirrors the service's canonicalization: recursively key-sorted JSON.
        $payload = ['title' => $english->title, 'summary' => $english->summary, 'body' => $english->body];
        ksort($payload);
        $expectedHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->assertSame($expectedHash, (string) $snapshot->english_content_hash);
    }

    /* E. Snapshot primary photo reference + hash. */
    public function test_snapshot_contains_primary_photo_reference_and_hash(): void
    {
        $pack = $this->seedPublishedProfile(withPhoto: true);
        $profile = $pack['profile']->fresh();
        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $photo = $pack['photo'];

        $this->assertSame((int) $photo->id, (int) $snapshot->primary_photo_media_id);
        $this->assertSame('public', (string) $snapshot->primary_photo_disk);
        $this->assertSame((string) $photo->storage_path_key, (string) $snapshot->primary_photo_key);
        $this->assertSame((string) $photo->photo_sha256, (string) $snapshot->primary_photo_sha256);
    }

    /* F. Authorized photo approval updates the snapshot. */
    public function test_photo_approval_updates_snapshot(): void
    {
        $pack = $this->seedPublishedProfile(withPhoto: true);
        $profile = $pack['profile']->fresh();
        $staffUploader = User::factory()->editor()->create();
        $reviewer = User::factory()->editor()->create();
        $media = app(ProfileMediaService::class);

        $before = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertCount(1, $before->photos ?? []);

        $second = $media->uploadProfilePhoto(
            $profile,
            UploadedFile::fake()->image('second.jpg', 300, 400),
            $staffUploader,
        );
        $media->approve($second, $reviewer);

        $after = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertCount(2, $after->photos ?? []);
        $this->assertSame('media.profile_photo_approved', (string) $after->source_event);
    }

    /* G. Authorized slug change updates the snapshot. */
    public function test_slug_change_updates_snapshot(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $staff = User::factory()->editor()->create();

        $candidate = app(ProfileUrlService::class)
            ->suggestPersonalSlugs('Integrity Monitor Leader', (int) $profile->id)[0]['slug'];
        app(ProfileUrlService::class)->selectPersonalSlug($profile, $staff, $candidate, 'accomplished');

        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame(strtolower($candidate), (string) $snapshot->slug);
    }

    /* H. Editorial replacement publication updates the snapshot. */
    public function test_editorial_replacement_updates_snapshot(): void
    {
        $pack = $this->seedPublishedProfile();
        $application = $pack['application']->fresh();
        $admin = User::factory()->admin()->create();

        $v2 = EditorialContent::query()->create([
            'profile_id' => $application->profile_id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 2,
            'title' => 'Replaced Title',
            'body' => 'Replacement approved body.',
            'summary' => 'Replacement summary.',
            'source_material' => 'replacement',
            'ai_generated' => false,
        ]);

        // Fresh customer approval of v2, then replacement publication.
        $application->fresh()->forceFill([
            'customer_approved_at' => now(),
            'customer_approved_english_editorial_content_id' => $v2->id,
            'status' => Application::STATUS_AWAITING_PUBLICATION,
        ])->save();

        app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);

        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $application->profile_id)->firstOrFail();
        $this->assertSame((int) $v2->id, (int) $snapshot->english_editorial_content_id);
        $this->assertSame('application.published.replacement', (string) $snapshot->source_event);
    }

    /* I. Video-link approval flows into the snapshot and verifies healthy. */
    public function test_video_link_change_reconciles_as_healthy(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $editor = User::factory()->editor()->create();
        $videos = app(ExternalVideoLinkService::class);

        $pending = $videos->submit(
            $profile,
            $pack['member'],
            'https://www.youtube.com/watch?v=abc123xyz',
            'Interview',
        );
        $videos->approve($pending, $editor);

        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertNotEmpty($snapshot->video_links);
        $this->assertSame('media.external_video_approved', (string) $snapshot->source_event);

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);
        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_HEALTHY, $result['classification']);
    }

    /* J. Unchanged state + intact object → HEALTHY. */
    public function test_unchanged_profile_and_object_is_healthy(): void
    {
        $pack = $this->seedPublishedProfile(withPhoto: true);
        $profile = $pack['profile']->fresh();

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_HEALTHY, $result['classification']);
        $this->assertSame([], $result['incidents']);
        $this->assertNotNull(
            ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->first()->verified_at
        );
    }

    /* K. Database media-reference substitution is detected. */
    public function test_media_reference_substitution_is_detected(): void
    {
        $pack = $this->seedPublishedProfile(withPhoto: true);
        $profile = $pack['profile']->fresh();
        $photo = $pack['photo'];

        // Raw DB tamper: repoint the stored key without any application path.
        $photo->forceFill(['storage_path_key' => 'profile-media/profiles/999/tampered.jpg'])->save();

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_MISMATCH, $result['classification']);
        $incident = IntegrityIncident::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame(IntegrityIncident::TYPE_PHOTOGRAPH_REFERENCE, (string) $incident->type);
        $this->assertSame('report', (string) $incident->mode);

        // Q (partial): REPORT mode — nothing suspended, nothing modified.
        $this->assertSame('published', (string) $profile->fresh()->status);
        $this->assertNull($profile->fresh()->suspended_at);
    }

    /* L. Underlying object modification detected via streamed SHA-256. */
    public function test_object_modification_is_detected_by_sha256(): void
    {
        $pack = $this->seedPublishedProfile(withPhoto: true);
        $profile = $pack['profile']->fresh();
        $photo = $pack['photo'];

        // Overwrite the stored object at the SAME key with different bytes.
        Storage::disk('public')->put($photo->storage_path_key, 'tampered-object-bytes');

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_MISMATCH, $result['classification']);
        $incident = IntegrityIncident::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame(IntegrityIncident::TYPE_PHOTOGRAPH_OBJECT, (string) $incident->type);
    }

    /* M. Mismatch explained by a matching newer audit event → AUTHORIZED_CHANGE. */
    public function test_mismatch_with_explaining_event_is_authorized_change(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $staff = User::factory()->editor()->create();

        // Stale-snapshot simulation: authorized change recorded through the
        // audit path but with the snapshot-refresh step skipped. The snapshot
        // is backdated so the event is strictly newer (second-precision clocks).
        $oldSlug = strtolower((string) $profile->slug);
        $newSlug = 'authorized-new-url';
        $profile->forceFill([
            'previous_slug' => $profile->slug,
            'slug' => $newSlug,
            'slug_changed_at' => now(),
        ])->save();
        ProfileIntegritySnapshot::query()
            ->where('profile_id', $profile->id)
            ->update(['updated_at' => now()->subMinute()]);

        StaffActionLog::query()->create([
            'actor_user_id' => $staff->id,
            'action' => 'profile_url.slug_changed',
            'subject_type' => (new Profile)->getMorphClass(),
            'subject_id' => $profile->id,
            'before' => ['slug' => $oldSlug],
            'after' => ['slug' => $newSlug, 'previous_slug' => $oldSlug],
            'ip_address' => '127.0.0.1',
        ]);

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_AUTHORIZED_CHANGE, $result['classification']);
        $this->assertSame([], $result['incidents']);
        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame($newSlug, (string) $snapshot->slug);
        $this->assertSame('watchdog_reconciled', (string) $snapshot->source_event);
    }

    /* O. Infrastructure failure (object unreachable) → SCAN_ERROR, never an incident. */
    public function test_unreachable_object_is_scan_error_not_incident(): void
    {
        $pack = $this->seedPublishedProfile(withPhoto: true);
        $profile = $pack['profile']->fresh();
        $photo = $pack['photo'];

        Storage::disk('public')->delete($photo->storage_path_key);

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_SCAN_ERROR, $result['classification']);
        $this->assertSame(0, IntegrityIncident::query()->count());
    }

    /* P. Circuit breaker prevents mass incident creation. */
    public function test_circuit_breaker_stops_mass_mismatch_handling(): void
    {
        $profileIds = [];
        for ($i = 0; $i < 6; $i++) {
            $pack = $this->seedPublishedProfile(slugSuffix: $i);
            $profileIds[] = $pack['profile']->id;
        }

        // Tamper 5 of 6 with no authorizing events. Threshold: max(3, 10%) = 3.
        foreach (array_slice($profileIds, 0, 5) as $id) {
            Profile::query()->whereKey($id)->update(['slug' => 'tampered-'.$id]);
        }

        Artisan::call('jannayaks:verify-profile-integrity');
        $output = Artisan::output();

        // Breaker tripped after the 3rd mismatch: at most 3 incidents created.
        $this->assertSame(3, IntegrityIncident::query()->count());
        $this->assertStringContainsString('circuit_broken', $output);
        $this->assertStringContainsString('unscanned_after_breaker', $output);
        $this->assertStringContainsString('REPORT-ONLY', $output);
    }

    /* Q. REPORT mode never suspends a profile with a confirmed mismatch. */
    public function test_report_mode_never_suspends(): void
    {
        $pack = $this->seedPublishedProfile(withPhoto: true);
        $profile = $pack['profile']->fresh();
        Storage::disk('public')->put($pack['photo']->storage_path_key, 'evil-bytes');

        Artisan::call('jannayaks:verify-profile-integrity');

        $fresh = $profile->fresh();
        $this->assertSame('published', (string) $fresh->status);
        $this->assertNull($fresh->suspended_at);
        $this->assertNull($fresh->unpublished_at);
        $this->assertTrue(app(ProfileUrlService::class)->isPubliclyVisible($fresh));
        $this->assertSame(1, IntegrityIncident::query()->count());
    }

    /* R. Re-running on an unchanged condition does not duplicate incidents. */
    public function test_rerun_does_not_duplicate_incidents(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        Profile::query()->whereKey($profile->id)->update(['slug' => 'tampered-once']);

        Artisan::call('jannayaks:verify-profile-integrity');
        Artisan::call('jannayaks:verify-profile-integrity');

        $open = IntegrityIncident::query()
            ->where('profile_id', $profile->id)
            ->where('status', IntegrityIncident::STATUS_OPEN)
            ->get();
        $this->assertCount(1, $open);
    }

    /* S. Profiles are processed independently. */
    public function test_profiles_processed_independently(): void
    {
        $clean = $this->seedPublishedProfile(withPhoto: true);
        $dirty = $this->seedPublishedProfile(slugSuffix: 1);
        Profile::query()->whereKey($dirty['profile']->id)->update(['slug' => 'tampered-dirty']);

        Artisan::call('jannayaks:verify-profile-integrity');

        $this->assertSame(0, IntegrityIncident::query()->where('profile_id', $clean['profile']->id)->count());
        $this->assertSame(1, IntegrityIncident::query()->where('profile_id', $dirty['profile']->id)->count());
        $this->assertNotNull(
            ProfileIntegritySnapshot::query()->where('profile_id', $clean['profile']->id)->first()->verified_at
        );
    }

    /* T. The watchdog never modifies public content. */
    public function test_watchdog_never_modifies_public_content(): void
    {
        $pack = $this->seedPublishedProfile(withPhoto: true);
        $profile = $pack['profile']->fresh();
        $photo = $pack['photo'];
        $bytesBefore = Storage::disk('public')->get($photo->storage_path_key);
        $profileBefore = $profile->getAttributes();
        $photoBefore = $photo->getAttributes();

        // Both a healthy profile and a tampered one in the same run.
        $dirty = $this->seedPublishedProfile(slugSuffix: 9);
        Profile::query()->whereKey($dirty['profile']->id)->update(['slug' => 'tampered-tt']);

        Artisan::call('jannayaks:verify-profile-integrity');

        $this->assertSame($profileBefore, $profile->fresh()->getAttributes());
        $this->assertSame($photoBefore, $photo->fresh()->getAttributes());
        $this->assertSame($bytesBefore, Storage::disk('public')->get($photo->storage_path_key));
    }

    /* Bootstrap: missing snapshot for a published profile is created, not flagged. */
    public function test_missing_snapshot_is_bootstrapped_without_incident(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->delete();

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_BOOTSTRAP, $result['classification']);
        $this->assertSame(0, IntegrityIncident::query()->count());
        $this->assertNotNull(ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->first());
    }

    /* Scheduler registration + defaults. */
    public function test_watchdog_is_registered_on_twelve_hour_schedule(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString('verify-profile-integrity', $output);
        $this->assertSame('report', (string) config('jannayaks.integrity.mode'));
    }

    /* Rerun idempotence of the whole command on a fully healthy corpus. */
    public function test_command_is_idempotent_on_healthy_corpus(): void
    {
        $this->seedPublishedProfile(withPhoto: true);

        Artisan::call('jannayaks:verify-profile-integrity');
        Artisan::call('jannayaks:verify-profile-integrity');

        $this->assertSame(0, IntegrityIncident::query()->count());
        $this->assertSame(1, ProfileIntegritySnapshot::query()->count());
    }

    /**
     * Seed a fully published living profile (optionally with an approved
     * public photograph and its object on the fake disk).
     *
     * @return array{member: User, application: Application, profile: Profile, photo?: MediaItem}
     */
    private function seedPublishedProfile(bool $withPhoto = false, int $slugSuffix = 0): array
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $editor = User::factory()->editor()->create();
        $reviewer = User::factory()->editor()->create();
        $admin = User::factory()->admin()->create();

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'package_tier' => 'accomplished',
            'source_method' => 'admin_test_demo',
            'full_name' => 'Integrity Monitor Leader',
            'preferred_display_name' => 'Integrity Monitor Leader',
        ]);

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'member_approved',
            'full_name' => 'Integrity Monitor Leader',
            'display_name' => 'Integrity Monitor Leader',
            'profession' => 'Public Servant',
            'slug' => 'integrity-leader-'.$slugSuffix.'-'.$this->randomSuffix(),
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application->forceFill(['profile_id' => $profile->id])->save();

        $pack = compact('member', 'application', 'profile');

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
            'title' => 'Integrity Monitor Leader',
            'body' => 'Approved English biography of the integrity leader.',
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

        app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);

        return $pack;
    }

    /**
     * @return array{0: User, 1: Application, 2: Profile}
     */
    private function seedPublishableApplication(): array
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'package_tier' => 'accomplished',
            'status' => Application::STATUS_AWAITING_PUBLICATION,
        ]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'member_approved',
            'full_name' => 'Not Yet Public',
            'display_name' => 'Not Yet Public',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application->forceFill(['profile_id' => $profile->id])->save();

        return [$member, $application->fresh(), $profile->fresh()];
    }

    private function randomSuffix(): string
    {
        return strtolower(bin2hex(random_bytes(3)));
    }
}
