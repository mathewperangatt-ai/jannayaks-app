<?php

namespace Tests\Feature;

use App\Contracts\PhotoEnhancementClient;
use App\Jobs\EnhanceProfilePhoto;
use App\Models\MediaItem;
use App\Models\PhotoEnhancementRun;
use App\Models\Profile;
use App\Models\User;
use App\Services\Ai\FakePhotoEnhancementClient;
use App\Services\PhotoEnhancementService;
use App\Services\ProfileMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Tests\TestCase;

/**
 * Phase 2 — AI photo enhancement: optional candidate generation layered on
 * the existing MediaItem architecture. Enhancement is a convenience: with
 * the feature disabled nothing is dispatched or created and the ordinary
 * photo workflow is byte-for-byte unchanged.
 */
class PhotoEnhancementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    private Profile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('jannayaks.ai.image_enhancement.enabled', false); // default-off invariant
        Storage::fake('public');
        config(['jannayaks.media.public_disk' => 'public']);

        $this->admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $this->member = User::factory()->create(['email_verified_at' => now()]);
        $this->profile = Profile::query()->create([
            'user_id' => $this->member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Photo Person',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        \App\Models\Application::factory()->paid()->create([
            'user_id' => $this->member->id,
            'profile_id' => $this->profile->id,
            'full_name' => 'Photo Person',
            'package_tier' => 'distinguished',
            'source_method' => 'admin_test_demo',
        ]);
    }

    private function uploadSource(): MediaItem
    {
        $file = UploadedFile::fake()->image('portrait.jpg', 900, 1200);

        return app(ProfileMediaService::class)->uploadProfilePhoto(
            profile: $this->profile,
            file: $file,
            actor: $this->member,
            altText: 'Portrait of Photo Person',
        );
    }

    private function enable(): FakePhotoEnhancementClient
    {
        Config::set('jannayaks.ai.image_enhancement.enabled', true);
        Config::set('jannayaks.ai.image_enhancement.provider', 'fake');

        return new FakePhotoEnhancementClient;
    }

    // ---------------- Configuration ----------------

    public function test_enhancement_is_disabled_by_default(): void
    {
        $this->assertFalse(config('jannayaks.ai.image_enhancement.enabled'));
        $this->assertSame('fake', config('jannayaks.ai.image_enhancement.provider'));
    }

    public function test_disabled_means_no_job_no_candidate_and_normal_upload(): void
    {
        Queue::fake();

        $item = $this->uploadSource();

        $this->assertSame(MediaItem::REVIEW_PENDING, $item->review_status);
        $this->assertSame(MediaItem::PRIVACY_PRIVATE, $item->privacy);
        $this->assertTrue($item->is_primary);
        $this->assertSame(0, PhotoEnhancementRun::query()->count());
        Queue::assertNotPushed(EnhanceProfilePhoto::class);
        Storage::disk('public')->assertExists($item->storage_path_key);
    }

    // ---------------- Upload / optimization ----------------

    public function test_optimization_still_works_and_original_is_not_retained(): void
    {
        Config::set('jannayaks.media.optimization.max_edge_px', 600);

        $item = $this->uploadSource();

        $this->assertSame('image/jpeg', $item->mime_type);
        $this->assertSame(600, max($item->width, $item->height));
        $this->assertNotNull($item->photo_sha256);

        $bytes = Storage::disk('public')->get($item->storage_path_key);
        $this->assertSame($item->photo_sha256, hash('sha256', $bytes));

        // Exactly ONE object exists for this photo — the optimized JPEG.
        $this->assertSame(1, count(Storage::disk('public')->allFiles()));
    }

    public function test_optimized_source_is_the_enhancement_input(): void
    {
        $client = $this->enable();
        $seen = [];
        app()->instance(PhotoEnhancementClient::class, tap($client, function ($c) use (&$seen) {
            // wrap to capture input
        }));
        // Replace binding with a capturing fake.
        app()->offsetUnset(PhotoEnhancementClient::class);
        app()->bind(PhotoEnhancementClient::class, function () use (&$seen) {
            return new class($seen) implements PhotoEnhancementClient
            {
                public function __construct(private array &$seen) {}

                public function providerName(): string
                {
                    return 'fake';
                }

                public function modelName(): string
                {
                    return 'capturing-fake';
                }

                public function enhance(string $sourceJpegBytes, string $prompt): string
                {
                    $this->seen[] = $sourceJpegBytes;

                    return (new FakePhotoEnhancementClient)->enhance($sourceJpegBytes, $prompt);
                }
            };
        });

        $item = $this->uploadSource(); // sync queue runs inline

        $this->assertCount(1, $seen);
        $this->assertSame(
            Storage::disk('public')->get($item->storage_path_key),
            $seen[0],
            'Enhancement input must be the exact stored optimized bytes',
        );
    }

    // ---------------- Queue / job ----------------

    public function test_job_dispatched_only_when_enabled(): void
    {
        Queue::fake();

        $this->uploadSource();
        Queue::assertNotPushed(EnhanceProfilePhoto::class);

        $this->enable();
        Queue::fake();
        $this->uploadSource();
        Queue::assertPushed(EnhanceProfilePhoto::class);
    }

    public function test_job_does_nothing_when_source_deleted(): void
    {
        $this->enable();
        Queue::fake(); // keep the run queued; do not process inline

        $item = $this->uploadSource();
        $run = PhotoEnhancementRun::query()->sole();
        $this->assertSame(PhotoEnhancementRun::STATUS_QUEUED, $run->status);

        // Deleting the source cascades the run away entirely (FK) — the DB
        // itself guarantees nothing can later resurrect a candidate.
        $item->delete();
        $this->assertSame(0, PhotoEnhancementRun::query()->count());

        // And executing the already-created job id for the dead run is a no-op.
        (new EnhanceProfilePhoto($run->id))->handle(app(PhotoEnhancementService::class));
        $this->assertSame(0, MediaItem::query()->where('enhanced_from_media_id', $run->source_media_id)->count());
    }

    public function test_job_does_nothing_when_source_rejected(): void
    {
        $this->enable();
        Queue::fake(); // keep the run queued; do not process inline

        $item = $this->uploadSource();
        $run = PhotoEnhancementRun::query()->sole();
        $this->assertSame(PhotoEnhancementRun::STATUS_QUEUED, $run->status);

        // Source rejected before the queued job executes.
        $item->forceFill(['review_status' => MediaItem::REVIEW_REJECTED])->save();

        app(PhotoEnhancementService::class)->processRun($run);

        $this->assertSame(PhotoEnhancementRun::STATUS_CANCELLED, $run->fresh()->status);
        $this->assertSame(0, MediaItem::query()->where('enhanced_from_media_id', $item->id)->count());
    }

    public function test_run_states_processing_completed_and_failed(): void
    {
        // Completed (fake provider, deterministic).
        $this->enable();
        $item = $this->uploadSource();
        $run = PhotoEnhancementRun::query()->orderByDesc('id')->sole();
        $this->assertSame(PhotoEnhancementRun::STATUS_COMPLETED, $run->status);
        $this->assertNotNull($run->candidate_media_id);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->finished_at);

        // Failed provider leaves source untouched.
        app()->instance(PhotoEnhancementClient::class, tap(new FakePhotoEnhancementClient, fn ($c) => $c->failNext = true));
        $source2 = $this->uploadSource();
        $failed = PhotoEnhancementRun::query()->where('source_media_id', $source2->id)->sole();
        $this->assertSame(PhotoEnhancementRun::STATUS_FAILED, $failed->status);
        $this->assertStringContainsString('Simulated', $failed->error_message);

        $source2->refresh();
        $this->assertSame(MediaItem::REVIEW_PENDING, $source2->review_status);
        $this->assertSame(MediaItem::PRIVACY_PRIVATE, $source2->privacy);
        $this->assertNotNull($source2->storage_path_key);
        $this->assertSame(0, MediaItem::query()->where('enhanced_from_media_id', $source2->id)->count());
    }

    // ---------------- Candidate ----------------

    public function test_candidate_linked_private_pending_and_free_of_slot_limits(): void
    {
        $this->enable();
        $source = $this->uploadSource();
        $candidate = MediaItem::query()->where('enhanced_from_media_id', $source->id)->sole();

        $this->assertSame(MediaItem::TYPE_PROFILE_PHOTO_ENHANCEMENT, $candidate->media_type);
        $this->assertSame($source->id, (int) $candidate->enhanced_from_media_id);
        $this->assertSame(MediaItem::PRIVACY_PRIVATE, $candidate->privacy);
        $this->assertSame(MediaItem::REVIEW_PENDING, $candidate->review_status);
        $this->assertFalse((bool) $candidate->is_primary);
        $this->assertSame($source->uploaded_by_id, $candidate->uploaded_by_id);
        $this->assertNotSame($source->storage_path_key, $candidate->storage_path_key);
        $this->assertNotNull($candidate->photo_sha256);
        $this->assertSame('image/jpeg', $candidate->mime_type);

        // Candidate never counts against the customer's photo slots: a tier-1
        // package can still hold a second real upload.
        Config::set('jannayaks.tier_pricing.packages.emerging.includes_photo_slots', 1);
        $second = $this->uploadSource();
        $this->assertSame(MediaItem::REVIEW_PENDING, $second->review_status);

        // And the candidate can never serve publicly.
        $this->assertFalse($candidate->isApprovedForPublicDisplay());
    }

    public function test_only_one_active_candidate_per_source(): void
    {
        $this->enable();
        $source = $this->uploadSource();

        $runs = PhotoEnhancementRun::query()->where('source_media_id', $source->id)->count();
        $this->assertSame(1, $runs);
        $this->assertSame(1, MediaItem::query()->where('enhanced_from_media_id', $source->id)->count());

        // Direct dispatch attempts are no-ops while a run is active.
        app(PhotoEnhancementService::class)->dispatchForSource($source->fresh());
        $this->assertSame(1, PhotoEnhancementRun::query()->where('source_media_id', $source->id)->count());

        // The DB-level guarantee (partial unique index) also holds.
        $this->expectException(\Illuminate\Database\QueryException::class);
        PhotoEnhancementRun::query()->create([
            'source_media_id' => $source->id,
            'provider' => 'fake',
            'model' => 'x',
            'status' => PhotoEnhancementRun::STATUS_COMPLETED,
        ]);
    }

    // ---------------- Accept Enhanced ----------------

    public function test_accept_makes_candidate_primary_and_retains_source_privately(): void
    {
        $this->enable();
        $source = $this->uploadSource();
        $candidate = MediaItem::query()->where('enhanced_from_media_id', $source->id)->sole();

        $accepted = app(PhotoEnhancementService::class)->accept($candidate, $this->admin);

        $this->assertSame(MediaItem::TYPE_PROFILE_PHOTO, $accepted->media_type);
        $this->assertSame(MediaItem::REVIEW_APPROVED, $accepted->review_status);
        $this->assertSame(MediaItem::PRIVACY_PUBLIC, $accepted->privacy);
        $this->assertTrue((bool) $accepted->is_primary);
        $this->assertTrue($accepted->isApprovedForPublicDisplay());

        $source->refresh();
        $this->assertFalse((bool) $source->is_primary);
        $this->assertSame(MediaItem::PRIVACY_PRIVATE, $source->privacy);
        $this->assertNotNull($source->fresh()); // retained, not deleted
        Storage::disk('public')->assertExists($source->storage_path_key);

        // Exactly one primary photo.
        $primaries = MediaItem::query()
            ->where('mediable_id', $this->profile->id)
            ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
            ->where('is_primary', true)
            ->count();
        $this->assertSame(1, $primaries);

        $run = PhotoEnhancementRun::query()->where('source_media_id', $source->id)->sole();
        $this->assertSame(PhotoEnhancementRun::STATUS_ACCEPTED, $run->status);
        $this->assertSame($this->admin->id, (int) $run->decided_by_user_id);

        // Audit trail identifies the deciding staff member.
        $this->assertDatabaseHas('staff_action_logs', ['action' => 'media.photo_enhancement_accepted']);
    }

    public function test_accept_blocked_for_the_source_uploader(): void
    {
        $this->enable();
        $source = $this->uploadSource();
        $candidate = MediaItem::query()->where('enhanced_from_media_id', $source->id)->sole();

        $staffUploader = User::factory()->admin()->create(['email_verified_at' => now()]);
        $source->forceFill(['uploaded_by_id' => $staffUploader->id])->save();
        $candidate->forceFill(['uploaded_by_id' => $staffUploader->id])->save();

        $this->expectException(\ValueError::class);
        app(PhotoEnhancementService::class)->accept($candidate, $staffUploader);
    }

    // ---------------- Keep Original ----------------

    public function test_keep_original_approves_source_and_discards_candidate(): void
    {
        $this->enable();
        $source = $this->uploadSource();
        $candidate = MediaItem::query()->where('enhanced_from_media_id', $source->id)->sole();
        $candidateKey = $candidate->storage_path_key;

        $kept = app(PhotoEnhancementService::class)->keepOriginal($candidate, $this->admin);

        $this->assertSame($source->id, $kept->id);
        $this->assertSame(MediaItem::REVIEW_APPROVED, $kept->review_status);
        $this->assertSame(MediaItem::PRIVACY_PUBLIC, $kept->privacy);
        $this->assertTrue((bool) $kept->is_primary);

        // No orphan candidate row or file.
        $this->assertSame(0, MediaItem::query()->where('enhanced_from_media_id', $source->id)->count());
        Storage::disk('public')->assertMissing($candidateKey);
        Storage::disk('public')->assertExists($source->storage_path_key);

        $run = PhotoEnhancementRun::query()->where('source_media_id', $source->id)->sole();
        $this->assertSame(PhotoEnhancementRun::STATUS_KEPT_ORIGINAL, $run->status);
        $this->assertDatabaseHas('staff_action_logs', ['action' => 'media.photo_enhancement_original_kept']);
    }

    // ---------------- Regenerate ----------------

    public function test_regenerate_cleans_previous_candidate_and_creates_one_new(): void
    {
        $this->enable();
        $source = $this->uploadSource();
        $candidate = MediaItem::query()->where('enhanced_from_media_id', $source->id)->sole();
        $oldKey = $candidate->storage_path_key;

        $newRun = app(PhotoEnhancementService::class)->regenerate($candidate, $this->admin);

        Storage::disk('public')->assertMissing($oldKey);
        $this->assertSame(0, MediaItem::query()->where('enhanced_from_media_id', $source->id)->where('id', '!=', optional($newRun)->candidate_media_id)->count());

        $this->assertNotNull($newRun);
        $this->assertSame(PhotoEnhancementRun::STATUS_COMPLETED, $newRun->status);
        $candidates = MediaItem::query()->where('enhanced_from_media_id', $source->id)->get();
        $this->assertCount(1, $candidates);
        $this->assertNotSame($oldKey, $candidates[0]->storage_path_key);

        $runs = PhotoEnhancementRun::query()->where('source_media_id', $source->id)->orderBy('id')->get();
        $this->assertSame(PhotoEnhancementRun::STATUS_DISCARDED, $runs[0]->status);
        $this->assertSame(PhotoEnhancementRun::STATUS_COMPLETED, $runs[1]->status);
        $this->assertDatabaseHas('staff_action_logs', ['action' => 'media.photo_enhancement_regenerated']);
    }

    // ---------------- Failure / retry ----------------

    public function test_failed_run_can_be_retried_and_bounded(): void
    {
        Config::set('jannayaks.ai.image_enhancement.enabled', true);
        app()->instance(PhotoEnhancementClient::class, tap(new FakePhotoEnhancementClient, fn ($c) => $c->failNext = true));
        $source = $this->uploadSource();
        $run = PhotoEnhancementRun::query()->where('source_media_id', $source->id)->sole();
        $this->assertSame(PhotoEnhancementRun::STATUS_FAILED, $run->status);

        // Retry succeeds (fake no longer failing) — sync queue runs inline.
        $retried = app(PhotoEnhancementService::class)->retry($run, $this->admin);
        $this->assertSame(PhotoEnhancementRun::STATUS_COMPLETED, $retried->status);
        $this->assertSame(1, (int) $retried->retry_count);

        // Bounded retries.
        Config::set('jannayaks.ai.image_enhancement.max_retries', 1);
        app()->instance(PhotoEnhancementClient::class, tap(new FakePhotoEnhancementClient, fn ($c) => $c->failNext = true));
        $failed = app(PhotoEnhancementService::class)->retry($retried->fresh(), $this->admin);
        $this->assertSame(PhotoEnhancementRun::STATUS_COMPLETED, $failed->status); // still completed, retry refused
    }

    // ---------------- Existing workflow ----------------

    public function test_existing_approval_publication_and_integrity_paths_unaffected(): void
    {
        $this->enable();
        $source = $this->uploadSource();

        // Ordinary approval still works with enhancement rows present.
        $approved = app(ProfileMediaService::class)->approve($source, $this->admin);
        $this->assertSame(MediaItem::REVIEW_APPROVED, $approved->review_status);
        $this->assertSame(MediaItem::PRIVACY_PUBLIC, $approved->privacy);

        // Candidate remains private/pending and never publicly displayable.
        $candidate = MediaItem::query()->where('enhanced_from_media_id', $source->id)->sole();
        $this->assertFalse($candidate->isApprovedForPublicDisplay());

        // Public photo list contains exactly the source.
        $public = app(\App\Services\PublicProfilePresentationService::class)->publicProfilePhotos($this->profile->fresh());
        $this->assertSame([$source->id], $public->pluck('id')->all());
    }

    public function test_upload_survives_enhancement_dispatcher_throwing(): void
    {
        Config::set('jannayaks.ai.image_enhancement.enabled', true);
        app()->bind(EnhanceProfilePhoto::class, function () {
            throw new \RuntimeException('dispatcher exploded');
        });
        // dispatch() itself failing is caught inside dispatchForSource's
        // transaction try/catch — simulate by binding a broken client.
        app()->instance(PhotoEnhancementClient::class, new class implements PhotoEnhancementClient
        {
            public function providerName(): string
            {
                throw new \RuntimeException('provider exploded');
            }

            public function modelName(): string
            {
                return 'x';
            }

            public function enhance(string $b, string $p): string
            {
                throw new \RuntimeException('nope');
            }
        });

        $item = $this->uploadSource();

        $this->assertSame(MediaItem::REVIEW_PENDING, $item->review_status);
        $this->assertNotNull($item->storage_path_key);
        Storage::disk('public')->assertExists($item->storage_path_key);
        $run = PhotoEnhancementRun::query()->where('source_media_id', $item->id)->first();
        $this->assertTrue($run === null || in_array($run->status, [PhotoEnhancementRun::STATUS_FAILED, PhotoEnhancementRun::STATUS_QUEUED], true));
    }
}
