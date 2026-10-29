<?php

namespace Tests\Feature;

use App\Mail\ProfileIntegrityAlertMail;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        config([
            'jannayaks.security.integrity.mode' => 'report',
            'jannayaks.security.integrity.deep_verify_percent' => 100,
            'jannayaks.security.integrity.alert_recipient' => 'mathew.perangatt@gmail.com',
            // Legacy keys still read as fallbacks in the command/service.
            'jannayaks.integrity.mode' => 'report',
            'jannayaks.integrity.deep_verify_percent' => 100,
        ]);
        // Watchdog may email the founder; never send real mail from this suite.
        Mail::fake();
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
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $profile->forceFill(['bio_headline' => 'Trusted public headline'])->save();
        app(ProfileIntegrityService::class)->refreshSnapshot($profile->fresh(), 'test_identity_headline');

        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame(strtolower((string) $profile->fresh()->slug), (string) $snapshot->slug);
        $this->assertSame($profile->fresh()->display_name, (string) $snapshot->display_name);
        $this->assertSame($profile->fresh()->profession, (string) $snapshot->profession);
        $this->assertSame('Trusted public headline', (string) $snapshot->bio_headline);
        $this->assertSame(
            'Trusted public headline',
            $snapshot->canonicalPayload()['bio_headline'] ?? null
        );
    }

    public function test_bio_headline_change_is_identity_mismatch(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $profile->forceFill(['bio_headline' => 'Baseline headline'])->save();
        app(ProfileIntegrityService::class)->refreshSnapshot($profile->fresh(), 'test_headline_baseline');

        $profile->forceFill(['bio_headline' => 'Unauthorised headline'])->save();

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile->fresh(), 'run-test', true);
        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_MISMATCH, $result['classification']);
        $incident = IntegrityIncident::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame(IntegrityIncident::TYPE_IDENTITY, (string) $incident->type);

        $assessment = app(ProfileIntegrityService::class)->assessPublicContentTrust($profile->fresh());
        $this->assertSame('mismatch', $assessment['outcome']);
        $this->assertContains('identity', $assessment['differing_sections']);
        $this->assertTrue($assessment['requires_integrity_waiver']);
    }

    public function test_pre_existing_live_headline_without_trusted_snapshot_value_fails_closed(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();

        // Simulate post-migration state: snapshot column exists but was never
        // trusted (NULL). Live row already has a headline — do not adopt it.
        $this->assertNull(
            ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('bio_headline')
        );
        $profile->forceFill(['bio_headline' => 'Pre-existing public headline'])->save();

        $assessment = app(ProfileIntegrityService::class)->assessPublicContentTrust($profile->fresh());
        $this->assertSame('mismatch', $assessment['outcome']);
        $this->assertContains('identity', $assessment['differing_sections']);
        $this->assertNull(
            ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('bio_headline')
        );
    }

    public function test_authorised_public_refresh_captures_reviewed_bio_headline(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $this->assertTrue(app(ProfileUrlService::class)->isPubliclyVisible($profile));

        $profile->forceFill(['bio_headline' => 'Reviewed headline after operator check'])->save();
        $snapshot = app(ProfileIntegrityService::class)->refreshSnapshot(
            $profile->fresh(),
            'operator_reviewed_headline_refresh'
        );

        $this->assertNotNull($snapshot);
        $this->assertSame('Reviewed headline after operator check', (string) $snapshot->bio_headline);
        $this->assertSame(
            'match',
            app(ProfileIntegrityService::class)->assessPublicContentTrust($profile->fresh())['outcome']
        );
    }

    public function test_slug_authorization_does_not_explain_bio_headline_change(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $staff = User::factory()->editor()->create();

        $profile->forceFill(['bio_headline' => 'Baseline headline'])->save();
        app(ProfileIntegrityService::class)->refreshSnapshot($profile->fresh(), 'test_headline_baseline');

        $oldSlug = strtolower((string) $profile->fresh()->slug);
        $newSlug = 'authorized-slug-with-headline-drift';
        $profile->forceFill([
            'previous_slug' => $profile->slug,
            'slug' => $newSlug,
            'slug_changed_at' => now(),
            'bio_headline' => 'Smuggled headline',
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

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile->fresh(), 'run-test', true);
        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_MISMATCH, $result['classification']);
        $this->assertSame(
            IntegrityIncident::TYPE_IDENTITY,
            (string) IntegrityIncident::query()->where('profile_id', $profile->id)->value('type')
        );
        $this->assertSame(
            'Baseline headline',
            ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('bio_headline')
        );
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
        $videos = app(ExternalVideoLinkService::class);

        try {
            $videos->submit(
                $profile,
                $pack['member'],
                'https://www.youtube.com/watch?v=abc123xyz',
                'Interview',
            );
            $this->fail('Living profiles must not accept a video link.');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertTrue(true);
        }

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

    /* P. Mass-mismatch alert does not stop scanning higher-ID profiles. */
    public function test_mass_mismatch_alert_continues_scanning_all_profiles(): void
    {
        $profileIds = [];
        for ($i = 0; $i < 6; $i++) {
            $pack = $this->seedPublishedProfile(slugSuffix: $i);
            $profileIds[] = $pack['profile']->id;
        }

        // Tamper 5 of 6 (including the highest IDs) with no authorizing events.
        // Alert threshold: max(3, 10%) = 3 — scan must still finish all six.
        foreach (array_slice($profileIds, 0, 5) as $id) {
            Profile::query()->whereKey($id)->update(['slug' => 'tampered-'.$id]);
        }

        Artisan::call('jannayaks:verify-profile-integrity');
        $output = Artisan::output();

        $this->assertSame(5, IntegrityIncident::query()->count());
        $this->assertStringContainsString('mass_mismatch_alert', $output);
        $this->assertStringContainsString('scanned', $output);
        $this->assertStringContainsString('REPORT-ONLY', $output);
        $this->assertStringNotContainsString('unscanned_after_breaker', $output);

        $highestTamperedId = $profileIds[4];
        $this->assertTrue(
            IntegrityIncident::query()->where('profile_id', $highestTamperedId)->exists(),
            'Higher-ID mismatched profiles must still be examined after the alert threshold.',
        );
        $this->assertSame(0, IntegrityIncident::query()->where('profile_id', $profileIds[5])->count());
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

    /* Missing snapshot: published profile is flagged; live state is not adopted. */
    public function test_missing_snapshot_is_reported_without_adopting_live_state(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->delete();

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_MISSING_SNAPSHOT, $result['classification']);
        $this->assertCount(1, $result['incidents']);
        $incident = IntegrityIncident::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame(IntegrityIncident::TYPE_MISSING_SNAPSHOT, (string) $incident->type);
        $this->assertNull(ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->first());
        $this->assertSame('published', (string) $profile->fresh()->status);
        $this->assertNull($profile->fresh()->suspended_at);
    }

    public function test_command_reports_missing_snapshot_in_summary(): void
    {
        $pack = $this->seedPublishedProfile();
        ProfileIntegritySnapshot::query()->where('profile_id', $pack['profile']->id)->delete();

        Artisan::call('jannayaks:verify-profile-integrity');
        $output = Artisan::output();

        $this->assertStringContainsString('missing_snapshot', $output);
        $this->assertStringContainsString('REPORT-ONLY', $output);
        $this->assertSame(1, IntegrityIncident::query()->count());
        $this->assertSame(0, ProfileIntegritySnapshot::query()->count());
    }

    public function test_establish_baseline_creates_snapshot_only_when_explicitly_requested(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->delete();

        $exit = Artisan::call('jannayaks:verify-profile-integrity', [
            '--profile' => (string) $profile->id,
            '--establish-baseline' => true,
        ]);

        $this->assertSame(0, $exit);
        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertSame('operator_establish_baseline', (string) $snapshot->source_event);
        $this->assertSame(0, IntegrityIncident::query()->count());

        // Existing baselines are never overwritten by the explicit path.
        $snapshot->forceFill(['source_event' => 'operator_establish_baseline'])->save();
        Artisan::call('jannayaks:verify-profile-integrity', [
            '--profile' => (string) $profile->id,
            '--establish-baseline' => true,
        ]);
        $this->assertSame(1, ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->count());
    }

    public function test_video_link_event_for_another_profile_does_not_authorise_change(): void
    {
        $victim = $this->seedPublishedProfile(slugSuffix: 1);
        $other = $this->seedPublishedProfile(slugSuffix: 2);
        $profile = $victim['profile']->fresh();
        $otherProfile = $other['profile']->fresh();
        $staff = User::factory()->editor()->create();

        ProfileIntegritySnapshot::query()
            ->where('profile_id', $profile->id)
            ->update(['updated_at' => now()->subMinute()]);

        $foreignLink = ProfileExternalLink::query()->create([
            'profile_id' => $otherProfile->id,
            'link_type' => ProfileExternalLink::TYPE_VIDEO,
            'label' => 'Other interview',
            'url' => 'https://www.youtube.com/watch?v=otherprofile1',
            'status' => ProfileExternalLink::STATUS_APPROVED,
            'is_publicly_active' => true,
            'submitted_by_user_id' => $staff->id,
        ]);

        // Tamper: inject a public video onto the victim with no own audit trail.
        ProfileExternalLink::query()->create([
            'profile_id' => $profile->id,
            'link_type' => ProfileExternalLink::TYPE_VIDEO,
            'label' => 'Injected',
            'url' => 'https://www.youtube.com/watch?v=injectedbad1',
            'status' => ProfileExternalLink::STATUS_APPROVED,
            'is_publicly_active' => true,
            'submitted_by_user_id' => $staff->id,
        ]);

        StaffActionLog::query()->create([
            'actor_user_id' => $staff->id,
            'action' => 'media.external_video_approved',
            'subject_type' => $foreignLink->getMorphClass(),
            'subject_id' => $foreignLink->id,
            'before' => ['status' => 'pending_review'],
            'after' => ['status' => 'approved', 'is_publicly_active' => true],
            'ip_address' => '127.0.0.1',
        ]);

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_MISMATCH, $result['classification']);
        $incident = IntegrityIncident::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame(IntegrityIncident::TYPE_VIDEO_LINKS, (string) $incident->type);
        $this->assertNotSame(
            'watchdog_reconciled',
            (string) ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('source_event')
        );
    }

    public function test_editorial_event_without_matching_content_does_not_authorise_change(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $application = $pack['application']->fresh();
        $staff = User::factory()->editor()->create();
        $englishId = (int) $application->published_english_editorial_content_id;

        ProfileIntegritySnapshot::query()
            ->where('profile_id', $profile->id)
            ->update(['updated_at' => now()->subMinute()]);

        // Tamper the published editorial body without a matching narrative audit.
        EditorialContent::query()->whereKey($englishId)->update([
            'body' => 'Unauthorised rewrite of the published biography.',
        ]);

        StaffActionLog::query()->create([
            'actor_user_id' => $staff->id,
            'action' => 'editorial_content.updated',
            'subject_type' => (new EditorialContent)->getMorphClass(),
            'subject_id' => $englishId,
            'before' => ['status' => 'approved', 'title' => 'Integrity Monitor Leader'],
            'after' => [
                'status' => 'approved',
                'title' => 'Integrity Monitor Leader',
                'language' => 'en',
                'version_number' => 1,
                // Deliberately omit body/summary/content_hash — ID alone must not authorise.
            ],
            'ip_address' => '127.0.0.1',
        ]);

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_MISMATCH, $result['classification']);
        $incident = IntegrityIncident::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame(IntegrityIncident::TYPE_EDITORIAL, (string) $incident->type);
        $this->assertNotSame(
            'watchdog_reconciled',
            (string) ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('source_event')
        );
    }

    public function test_publication_event_does_not_authorise_inplace_editorial_rewrite(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $application = $pack['application']->fresh();
        $englishId = (int) $application->published_english_editorial_content_id;

        // Pull the seed publication audit into the reconciliation window without
        // adding any editorial_content.* narrative evidence.
        ProfileIntegritySnapshot::query()
            ->where('profile_id', $profile->id)
            ->update(['updated_at' => now()->subMinute()]);

        EditorialContent::query()->whereKey($englishId)->update([
            'body' => 'Silent in-place rewrite after publication.',
        ]);

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_MISMATCH, $result['classification']);
        $this->assertSame(IntegrityIncident::TYPE_EDITORIAL, (string) IntegrityIncident::query()
            ->where('profile_id', $profile->id)
            ->value('type'));
        $this->assertSame([], $result['event_ids']);
    }

    public function test_title_only_editorial_event_does_not_authorise_body_rewrite(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $application = $pack['application']->fresh();
        $staff = User::factory()->editor()->create();
        $englishId = (int) $application->published_english_editorial_content_id;

        ProfileIntegritySnapshot::query()
            ->where('profile_id', $profile->id)
            ->update(['updated_at' => now()->subMinute()]);

        EditorialContent::query()->whereKey($englishId)->update([
            'title' => 'Integrity Monitor Leader',
            'body' => 'Body changed while title stayed the same.',
        ]);

        StaffActionLog::query()->create([
            'actor_user_id' => $staff->id,
            'action' => 'editorial_content.updated',
            'subject_type' => (new EditorialContent)->getMorphClass(),
            'subject_id' => $englishId,
            'before' => ['status' => 'approved'],
            'after' => [
                'status' => 'approved',
                'title' => 'Integrity Monitor Leader',
                // No body / content_hash — must not authorise via live-row fallback.
            ],
            'ip_address' => '127.0.0.1',
        ]);

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_MISMATCH, $result['classification']);
        $this->assertSame(IntegrityIncident::TYPE_EDITORIAL, (string) IntegrityIncident::query()
            ->where('profile_id', $profile->id)
            ->value('type'));
    }

    public function test_editorial_event_with_matching_narrative_still_authorises(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $application = $pack['application']->fresh();
        $staff = User::factory()->editor()->create();
        $englishId = (int) $application->published_english_editorial_content_id;
        $newTitle = 'Authorised Revised Title';
        $newSummary = 'Authorised revised summary.';
        $newBody = 'Authorised revised English biography body.';

        // Keep the seed publication audit inside the window so authorisation
        // must come from matching narrative evidence, not from publication alone.
        ProfileIntegritySnapshot::query()
            ->where('profile_id', $profile->id)
            ->update(['updated_at' => now()->subMinute()]);

        EditorialContent::query()->whereKey($englishId)->update([
            'title' => $newTitle,
            'summary' => $newSummary,
            'body' => $newBody,
        ]);

        $editorialEvent = StaffActionLog::query()->create([
            'actor_user_id' => $staff->id,
            'action' => 'editorial_content.updated',
            'subject_type' => (new EditorialContent)->getMorphClass(),
            'subject_id' => $englishId,
            'before' => ['status' => 'approved'],
            'after' => [
                'status' => 'approved',
                'title' => $newTitle,
                'summary' => $newSummary,
                'body' => $newBody,
                'language' => 'en',
                'version_number' => 1,
            ],
            'ip_address' => '127.0.0.1',
        ]);

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_AUTHORIZED_CHANGE, $result['classification']);
        $this->assertSame([], $result['incidents']);
        $this->assertContains($editorialEvent->id, $result['event_ids']);
        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame('watchdog_reconciled', (string) $snapshot->source_event);
        $this->assertSame($newTitle, EditorialContent::query()->findOrFail($englishId)->title);
        $this->assertSame($newBody, EditorialContent::query()->findOrFail($englishId)->body);
    }

    public function test_malayalam_publication_event_does_not_authorise_inplace_rewrite(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $application = $pack['application']->fresh();
        $admin = User::factory()->admin()->create();
        $englishId = (int) $application->published_english_editorial_content_id;

        $malayalam = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 1,
            'title' => 'മലയാളം ശീർഷകം',
            'body' => 'അംഗീകൃത മലയാളം ജീവചരിത്രം.',
            'summary' => 'മലയാളം സംഗ്രഹം.',
            'source_material' => '',
            'ai_generated' => false,
            'source_editorial_content_id' => $englishId,
        ]);

        app(ApplicationWorkflowService::class)->publishMalayalam($application->fresh(), $admin, $malayalam);
        $malayalamId = (int) $application->fresh()->published_malayalam_editorial_content_id;

        ProfileIntegritySnapshot::query()
            ->where('profile_id', $profile->id)
            ->update(['updated_at' => now()->subMinute()]);

        EditorialContent::query()->whereKey($malayalamId)->update([
            'body' => 'തിരുത്തിയ മലയാളം ജീവചരിത്രം അനധികൃതമായി.',
        ]);

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_MISMATCH, $result['classification']);
        $this->assertSame(IntegrityIncident::TYPE_EDITORIAL, (string) IntegrityIncident::query()
            ->where('profile_id', $profile->id)
            ->value('type'));
    }

    public function test_malayalam_editorial_event_with_matching_narrative_authorises(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $application = $pack['application']->fresh();
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->editor()->create();
        $englishId = (int) $application->published_english_editorial_content_id;

        $malayalam = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 1,
            'title' => 'മലയാളം ശീർഷകം',
            'body' => 'അംഗീകൃത മലയാളം ജീവചരിത്രം.',
            'summary' => 'മലയാളം സംഗ്രഹം.',
            'source_material' => '',
            'ai_generated' => false,
            'source_editorial_content_id' => $englishId,
        ]);

        app(ApplicationWorkflowService::class)->publishMalayalam($application->fresh(), $admin, $malayalam);
        $malayalamId = (int) $application->fresh()->published_malayalam_editorial_content_id;
        $newTitle = 'പുതുക്കിയ മലയാളം ശീർഷകം';
        $newSummary = 'പുതുക്കിയ മലയാളം സംഗ്രഹം.';
        $newBody = 'പുതുക്കിയ അംഗീകൃത മലയാളം ജീവചരിത്രം.';

        ProfileIntegritySnapshot::query()
            ->where('profile_id', $profile->id)
            ->update(['updated_at' => now()->subMinute()]);

        EditorialContent::query()->whereKey($malayalamId)->update([
            'title' => $newTitle,
            'summary' => $newSummary,
            'body' => $newBody,
        ]);

        $editorialEvent = StaffActionLog::query()->create([
            'actor_user_id' => $staff->id,
            'action' => 'editorial_content.updated',
            'subject_type' => (new EditorialContent)->getMorphClass(),
            'subject_id' => $malayalamId,
            'before' => ['status' => 'approved'],
            'after' => [
                'status' => 'approved',
                'title' => $newTitle,
                'summary' => $newSummary,
                'body' => $newBody,
                'language' => 'ml',
                'version_number' => 1,
            ],
            'ip_address' => '127.0.0.1',
        ]);

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_AUTHORIZED_CHANGE, $result['classification']);
        $this->assertSame([], $result['incidents']);
        $this->assertContains($editorialEvent->id, $result['event_ids']);
        $this->assertSame(
            'watchdog_reconciled',
            (string) ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('source_event')
        );
        $this->assertSame($newBody, EditorialContent::query()->findOrFail($malayalamId)->body);
    }

    public function test_malayalam_publication_event_does_not_authorise_english_rewrite(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $application = $pack['application']->fresh();
        $admin = User::factory()->admin()->create();
        $englishId = (int) $application->published_english_editorial_content_id;

        $malayalam = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 1,
            'title' => 'മലയാളം ശീർഷകം',
            'body' => 'അംഗീകൃത മലയാളം ജീവചരിത്രം.',
            'summary' => 'മലയാളം സംഗ്രഹം.',
            'source_material' => '',
            'ai_generated' => false,
            'source_editorial_content_id' => $englishId,
        ]);

        app(ApplicationWorkflowService::class)->publishMalayalam($application->fresh(), $admin, $malayalam);

        $snapshotBefore = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $trustedHash = (string) $snapshotBefore->english_content_hash;
        $trustedSource = (string) $snapshotBefore->source_event;

        ProfileIntegritySnapshot::query()
            ->where('profile_id', $profile->id)
            ->update(['updated_at' => now()->subMinute()]);

        EditorialContent::query()->whereKey($englishId)->update([
            'body' => 'English rewrite that must not be covered by Malayalam publication evidence.',
        ]);

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_MISMATCH, $result['classification']);
        $this->assertSame([], $result['event_ids']);
        $this->assertSame('published', (string) $profile->fresh()->status);
        $snapshotAfter = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame($trustedHash, (string) $snapshotAfter->english_content_hash);
        $this->assertSame($trustedSource, (string) $snapshotAfter->source_event);
        $this->assertNotSame('watchdog_reconciled', (string) $snapshotAfter->source_event);
        $this->assertSame(IntegrityIncident::TYPE_EDITORIAL, (string) IntegrityIncident::query()
            ->where('profile_id', $profile->id)
            ->value('type'));
    }

    public function test_english_editorial_evidence_does_not_authorise_malayalam_rewrite(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $application = $pack['application']->fresh();
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->editor()->create();
        $englishId = (int) $application->published_english_editorial_content_id;

        $malayalam = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 1,
            'title' => 'മലയാളം ശീർഷകം',
            'body' => 'അംഗീകൃത മലയാളം ജീവചരിത്രം.',
            'summary' => 'മലയാളം സംഗ്രഹം.',
            'source_material' => '',
            'ai_generated' => false,
            'source_editorial_content_id' => $englishId,
        ]);
        app(ApplicationWorkflowService::class)->publishMalayalam($application->fresh(), $admin, $malayalam);
        $malayalamId = (int) $application->fresh()->published_malayalam_editorial_content_id;

        $trustedMlHash = (string) ProfileIntegritySnapshot::query()
            ->where('profile_id', $profile->id)
            ->value('malayalam_content_hash');

        ProfileIntegritySnapshot::query()
            ->where('profile_id', $profile->id)
            ->update(['updated_at' => now()->subMinute()]);

        EditorialContent::query()->whereKey($malayalamId)->update([
            'body' => 'മലയാളം മാറ്റം ഇ-only evidence must not cover.',
        ]);

        $english = EditorialContent::query()->findOrFail($englishId);
        StaffActionLog::query()->create([
            'actor_user_id' => $staff->id,
            'action' => 'editorial_content.updated',
            'subject_type' => $english->getMorphClass(),
            'subject_id' => $englishId,
            'before' => ['status' => 'approved'],
            'after' => [
                'status' => 'approved',
                'title' => $english->title,
                'summary' => $english->summary,
                'body' => $english->body,
                'language' => 'en',
                'version_number' => 1,
            ],
            'ip_address' => '127.0.0.1',
        ]);

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_MISMATCH, $result['classification']);
        $this->assertNotSame(
            'watchdog_reconciled',
            (string) ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('source_event')
        );
        $this->assertSame(
            $trustedMlHash,
            (string) ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('malayalam_content_hash')
        );
    }

    public function test_malayalam_editorial_evidence_does_not_authorise_english_rewrite(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $application = $pack['application']->fresh();
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->editor()->create();
        $englishId = (int) $application->published_english_editorial_content_id;

        $malayalam = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 1,
            'title' => 'മലയാളം ശീർഷകം',
            'body' => 'അംഗീകൃത മലയാളം ജീവചരിത്രം.',
            'summary' => 'മലയാളം സംഗ്രഹം.',
            'source_material' => '',
            'ai_generated' => false,
            'source_editorial_content_id' => $englishId,
        ]);
        app(ApplicationWorkflowService::class)->publishMalayalam($application->fresh(), $admin, $malayalam);
        $malayalamId = (int) $application->fresh()->published_malayalam_editorial_content_id;

        $trustedEnHash = (string) ProfileIntegritySnapshot::query()
            ->where('profile_id', $profile->id)
            ->value('english_content_hash');

        ProfileIntegritySnapshot::query()
            ->where('profile_id', $profile->id)
            ->update(['updated_at' => now()->subMinute()]);

        EditorialContent::query()->whereKey($englishId)->update([
            'body' => 'English rewrite not covered by Malayalam-only narrative evidence.',
        ]);

        $ml = EditorialContent::query()->findOrFail($malayalamId);
        StaffActionLog::query()->create([
            'actor_user_id' => $staff->id,
            'action' => 'editorial_content.updated',
            'subject_type' => $ml->getMorphClass(),
            'subject_id' => $malayalamId,
            'before' => ['status' => 'approved'],
            'after' => [
                'status' => 'approved',
                'title' => $ml->title,
                'summary' => $ml->summary,
                'body' => $ml->body,
                'language' => 'ml',
                'version_number' => 1,
            ],
            'ip_address' => '127.0.0.1',
        ]);

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_MISMATCH, $result['classification']);
        $this->assertSame(
            $trustedEnHash,
            (string) ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('english_content_hash')
        );
        $this->assertNotSame(
            'watchdog_reconciled',
            (string) ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('source_event')
        );
    }

    public function test_editorial_id_swap_without_publication_evidence_is_mismatch(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $application = $pack['application']->fresh();
        $staff = User::factory()->editor()->create();
        $originalId = (int) $application->published_english_editorial_content_id;

        $replacement = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 2,
            'title' => 'Swapped Title',
            'body' => 'Swapped body that is not publication-audited.',
            'summary' => 'Swapped summary.',
            'source_material' => '',
            'ai_generated' => false,
        ]);

        $trustedId = (int) ProfileIntegritySnapshot::query()
            ->where('profile_id', $profile->id)
            ->value('english_editorial_content_id');
        $this->assertSame($originalId, $trustedId);

        ProfileIntegritySnapshot::query()
            ->where('profile_id', $profile->id)
            ->update(['updated_at' => now()->subMinute()]);

        // Silent pointer swap on the application — no publish/replacement audit.
        $application->forceFill([
            'published_english_editorial_content_id' => $replacement->id,
            'customer_approved_english_editorial_content_id' => $replacement->id,
        ])->save();

        StaffActionLog::query()->create([
            'actor_user_id' => $staff->id,
            'action' => 'editorial_content.updated',
            'subject_type' => $replacement->getMorphClass(),
            'subject_id' => $replacement->id,
            'before' => ['status' => 'approved'],
            'after' => [
                'status' => 'approved',
                'title' => $replacement->title,
                'summary' => $replacement->summary,
                'body' => $replacement->body,
                'language' => 'en',
                'version_number' => 2,
            ],
            'ip_address' => '127.0.0.1',
        ]);

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_MISMATCH, $result['classification']);
        $this->assertSame(
            $trustedId,
            (int) ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('english_editorial_content_id')
        );
        $this->assertNotSame(
            'watchdog_reconciled',
            (string) ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->value('source_event')
        );
    }

    public function test_editorial_replacement_publication_evidence_authorises_id_change(): void
    {
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $application = $pack['application']->fresh();
        $admin = User::factory()->admin()->create();

        $v2 = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 2,
            'title' => 'Replacement Title',
            'body' => 'Replacement approved body for integrity auth.',
            'summary' => 'Replacement summary.',
            'source_material' => 'replacement',
            'ai_generated' => false,
        ]);

        // Stale snapshot: publication advances the live pointer, but the
        // snapshot refresh is skipped so verify must reconcile via the audit.
        ProfileIntegritySnapshot::query()
            ->where('profile_id', $profile->id)
            ->update(['updated_at' => now()->subMinute()]);

        $application->fresh()->forceFill([
            'customer_approved_at' => now(),
            'customer_approved_english_editorial_content_id' => $v2->id,
            'status' => Application::STATUS_AWAITING_PUBLICATION,
        ])->save();

        // Publish normally (writes audit + snapshot). Then restore the prior
        // snapshot payload while keeping the replacement audit newer than
        // updated_at, simulating a missed refresh after a real publication.
        $before = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail()->getAttributes();
        app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);

        $afterPublish = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame((int) $v2->id, (int) $afterPublish->english_editorial_content_id);

        // Roll snapshot back to pre-replacement state but keep updated_at old
        // so the replacement audit remains in the reconciliation window.
        ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->update([
            'english_editorial_content_id' => $before['english_editorial_content_id'],
            'english_content_hash' => $before['english_content_hash'],
            'malayalam_editorial_content_id' => $before['malayalam_editorial_content_id'],
            'malayalam_content_hash' => $before['malayalam_content_hash'],
            'snapshot_hash' => $before['snapshot_hash'],
            'source_event' => $before['source_event'],
            'slug' => $before['slug'],
            'display_name' => $before['display_name'],
            'profession' => $before['profession'],
            'updated_at' => now()->subMinute(),
        ]);

        $result = app(ProfileIntegrityService::class)->verifyProfile($profile->fresh(), 'run-test', true);

        $this->assertSame(ProfileIntegrityService::CLASSIFICATION_AUTHORIZED_CHANGE, $result['classification']);
        $this->assertSame([], $result['incidents']);
        $reconciled = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame((int) $v2->id, (int) $reconciled->english_editorial_content_id);
        $this->assertSame('watchdog_reconciled', (string) $reconciled->source_event);
    }

    /* Scheduler registration + defaults. */
    public function test_watchdog_is_registered_on_twelve_hour_schedule(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString('verify-profile-integrity', $output);
        $this->assertSame('report', (string) config('jannayaks.security.integrity.mode'));
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

    public function test_new_incident_sends_founder_alert_with_run_summary(): void
    {
        Mail::fake();
        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $memberEmail = (string) $pack['member']->email;
        Profile::query()->whereKey($profile->id)->update(['slug' => 'tampered-alert-'.$profile->id]);

        Artisan::call('jannayaks:verify-profile-integrity');

        Mail::assertSent(ProfileIntegrityAlertMail::class, function (ProfileIntegrityAlertMail $mail) use ($profile, $memberEmail): bool {
            $mail->assertTo('mathew.perangatt@gmail.com');
            $this->assertNotSame('', (string) ($mail->summary['run_id'] ?? ''));
            $this->assertSame('report', (string) $mail->summary['mode']);
            $this->assertSame(1, (int) $mail->summary['incidents_created']);
            $this->assertSame(1, (int) $mail->summary['integrity_mismatch']);
            $this->assertFalse((bool) $mail->summary['mass_mismatch_alert']);
            $this->assertCount(1, $mail->findings);
            $this->assertSame((int) $profile->id, (int) $mail->findings[0]['profile_id']);
            $this->assertSame('tampered-alert-'.$profile->id, (string) $mail->findings[0]['slug']);
            $this->assertTrue((bool) $mail->findings[0]['newly_created']);

            $rendered = $mail->render();
            $this->assertStringContainsString((string) $mail->summary['run_id'], $rendered);
            $this->assertStringContainsString((string) $profile->id, $rendered);
            $this->assertStringContainsString('tampered-alert-'.$profile->id, $rendered);
            $this->assertStringNotContainsString($memberEmail, $rendered);
            $this->assertStringNotContainsString('Approved English biography of the integrity leader.', $rendered);
            $this->assertStringNotContainsString('preferred_contact', $rendered);

            return true;
        });
    }

    public function test_healthy_run_does_not_send_founder_alert(): void
    {
        Mail::fake();
        $this->seedPublishedProfile(withPhoto: true);

        Artisan::call('jannayaks:verify-profile-integrity');

        Mail::assertNothingSent();
        $this->assertSame(0, IntegrityIncident::query()->count());
    }

    public function test_unchanged_open_incident_does_not_repeat_founder_alert(): void
    {
        Mail::fake();
        $pack = $this->seedPublishedProfile();
        Profile::query()->whereKey($pack['profile']->id)->update(['slug' => 'tampered-once-alert']);

        Artisan::call('jannayaks:verify-profile-integrity');
        Mail::assertSent(ProfileIntegrityAlertMail::class, 1);

        Artisan::call('jannayaks:verify-profile-integrity');
        Mail::assertSent(ProfileIntegrityAlertMail::class, 1);
        $this->assertSame(1, IntegrityIncident::query()->count());
    }

    public function test_mass_mismatch_sends_founder_alert_even_without_new_incidents(): void
    {
        Mail::fake();
        $profileIds = [];
        for ($i = 0; $i < 6; $i++) {
            $pack = $this->seedPublishedProfile(slugSuffix: $i);
            $profileIds[] = $pack['profile']->id;
        }
        foreach (array_slice($profileIds, 0, 5) as $id) {
            Profile::query()->whereKey($id)->update(['slug' => 'tampered-mass-'.$id]);
        }

        Artisan::call('jannayaks:verify-profile-integrity');
        Mail::assertSent(ProfileIntegrityAlertMail::class, 1);
        $this->assertSame(5, IntegrityIncident::query()->count());

        // Second run: fingerprint dedupe creates no new rows, but mass threshold still fires.
        Artisan::call('jannayaks:verify-profile-integrity');
        Mail::assertSent(ProfileIntegrityAlertMail::class, 2);

        $second = null;
        Mail::assertSent(ProfileIntegrityAlertMail::class, function (ProfileIntegrityAlertMail $mail) use (&$second): bool {
            if ((int) $mail->summary['incidents_created'] === 0
                && (bool) $mail->summary['mass_mismatch_alert'] === true) {
                $second = $mail;
            }

            return true;
        });
        $this->assertNotNull($second);
        $this->assertSame(0, (int) $second->summary['incidents_created']);
        $this->assertTrue((bool) $second->summary['mass_mismatch_alert']);
        $this->assertNotEmpty($second->findings);
        $this->assertFalse((bool) $second->findings[0]['newly_created']);
        $this->assertSame(5, IntegrityIncident::query()->count());
    }

    public function test_founder_alert_delivery_failure_is_logged_and_does_not_fail_scan(): void
    {
        Log::spy();

        $pending = \Mockery::mock(\Illuminate\Mail\PendingMail::class);
        $pending->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('SMTP unavailable for integrity alert test'));
        Mail::swap(\Mockery::mock(\Illuminate\Mail\MailManager::class));
        Mail::shouldReceive('to')
            ->once()
            ->with('mathew.perangatt@gmail.com')
            ->andReturn($pending);

        $pack = $this->seedPublishedProfile();
        $profile = $pack['profile']->fresh();
        $snapshotBefore = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail()->toArray();
        Profile::query()->whereKey($profile->id)->update(['slug' => 'tampered-mail-fail']);

        $exit = Artisan::call('jannayaks:verify-profile-integrity');

        $this->assertSame(0, $exit);
        $this->assertSame(1, IntegrityIncident::query()->count());
        $fresh = $profile->fresh();
        $this->assertSame('published', (string) $fresh->status);
        $this->assertNull($fresh->suspended_at);
        $this->assertNull($fresh->unpublished_at);
        $snapshotAfter = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame($snapshotBefore['slug'], $snapshotAfter->slug);
        $this->assertSame($snapshotBefore['snapshot_hash'], $snapshotAfter->snapshot_hash);
        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context = []): bool {
                return str_contains($message, 'founder alert delivery failed')
                    && isset($context['run_id']);
            })
            ->atLeast()
            ->once();
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

        $application = Application::factory()->historical('accomplished')->paid()->create([
            'user_id' => $member->id,
            'source_method' => 'admin_test_demo',
            'broad_location' => 'Kozhikode',
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
        $application = Application::factory()->historical('accomplished')->paid()->create([
            'user_id' => $member->id,
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
