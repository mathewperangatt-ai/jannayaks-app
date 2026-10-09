<?php

namespace App\Services;

use App\Contracts\PhotoEnhancementClient;
use App\Jobs\EnhanceProfilePhoto;
use App\Models\MediaItem;
use App\Models\PhotoEnhancementRun;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ValueError;

/**
 * AI photo enhancement — a convenience layered on the existing MediaItem
 * architecture, never a dependency:
 *
 *   optimized source MediaItem (customer upload, unchanged)
 *     → queued enhancement run (PhotoEnhancementRun provenance)
 *     → candidate MediaItem (media_type = profile_photo_enhancement,
 *       private, pending, linked via enhanced_from_media_id, occupies no
 *       customer photo slot and can never serve publicly)
 *     → admin decision: Accept Enhanced / Keep Original / Regenerate,
 *       all funneling back through the existing approval/publication
 *       invariants.
 */
class PhotoEnhancementService
{
    public function __construct(
        private StaffAuditLogger $audit,
        private ProfilePhotoOptimizer $optimizer,
    ) {}

    public function enabled(): bool
    {
        // Explicit kill switch (env value / runtime Config::set) always wins.
        // When unset (null), enhancement follows the TEMPORARY testing mode so
        // testers can exercise the complete workflow — and it auto-disables
        // when testing mode ends.
        $configured = config('jannayaks.ai.image_enhancement.enabled');

        return $configured !== null
            ? (bool) $configured
            : ApplicationPaymentStateService::testingModeActive();
    }

    public function client(): PhotoEnhancementClient
    {
        return app(PhotoEnhancementClient::class);
    }

    /**
     * A source photo is eligible while it exists as a normal customer
     * profile photo that has not been rejected.
     */
    public function sourceEligible(?MediaItem $source): bool
    {
        return $source instanceof MediaItem
            && $source->exists
            && $source->media_type === MediaItem::TYPE_PROFILE_PHOTO
            && $source->review_status !== MediaItem::REVIEW_REJECTED
            && filled($source->storage_path_key);
    }

    /** The active (queued/processing/completed) run for a source, if any. */
    public function activeRunFor(MediaItem $source): ?PhotoEnhancementRun
    {
        return PhotoEnhancementRun::query()
            ->where('source_media_id', $source->id)
            ->whereIn('status', PhotoEnhancementRun::ACTIVE_STATUSES)
            ->orderByDesc('id')
            ->first();
    }

    /** The most recent run of any status (for failure display / retry). */
    public function latestRunFor(MediaItem $source): ?PhotoEnhancementRun
    {
        return PhotoEnhancementRun::query()
            ->where('source_media_id', $source->id)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Automatically start an enhancement run for a freshly uploaded
     * optimized source. NEVER throws to the caller — any problem here must
     * leave the upload workflow untouched.
     */
    public function dispatchForSource(MediaItem $source, ?User $requester = null): ?PhotoEnhancementRun
    {
        try {
            if (! $this->enabled() || ! $this->sourceEligible($source)) {
                return null;
            }

            if ($this->activeRunFor($source) !== null) {
                return null; // one active candidate per source
            }

            $client = $this->client();

            return DB::transaction(function () use ($source, $requester, $client): PhotoEnhancementRun {
                // Re-check inside the transaction; the partial unique index
                // is the hard guarantee against duplicate active runs.
                if ($this->activeRunFor($source) !== null) {
                    return $this->activeRunFor($source);
                }

                $run = PhotoEnhancementRun::query()->create([
                    'source_media_id' => $source->id,
                    'provider' => $client->providerName(),
                    'model' => $client->modelName(),
                    'status' => PhotoEnhancementRun::STATUS_QUEUED,
                    'requested_by_user_id' => $requester?->id,
                ]);

                EnhanceProfilePhoto::dispatch($run->id);

                // Sync-queue drivers process inline above; return the
                // post-job state, not the pre-dispatch instance.
                return $run->fresh() ?? $run;
            });
        } catch (Throwable) {
            return null; // upload must never fail because of enhancement
        }
    }

    /**
     * Execute one queued run. Verifies eligibility again (the source may
     * have been rejected/deleted while queued) and normalizes the provider
     * output through the ordinary optimizer before storing the candidate.
     */
    public function processRun(PhotoEnhancementRun $run): PhotoEnhancementRun
    {
        if (! $this->enabled()) {
            $this->cancel($run, 'Enhancement disabled.');

            return $run;
        }

        $run = $run->fresh() ?? $run;
        if ($run->status !== PhotoEnhancementRun::STATUS_QUEUED) {
            return $run; // already processed/decided
        }

        $source = MediaItem::query()->find($run->source_media_id);
        if (! $this->sourceEligible($source)) {
            $this->cancel($run, 'Source photograph is no longer available.');

            return $run;
        }
        /** @var MediaItem $source */
        $profile = $source->mediable;
        if (! $profile instanceof Profile) {
            $this->cancel($run, 'Source photograph owner is invalid.');

            return $run;
        }

        $run->forceFill(['status' => PhotoEnhancementRun::STATUS_PROCESSING, 'started_at' => now()])->save();

        try {
            $disk = filled($source->disk) ? (string) $source->disk : 'public';
            $sourceBytes = Storage::disk($disk)->get($source->storage_path_key);
            if ($sourceBytes === null || $sourceBytes === '') {
                throw new \RuntimeException('Optimized source photograph could not be read from storage.');
            }

            $prompt = (string) config('jannayaks.ai.image_enhancement.prompt', '');
            $enhancedBytes = $this->client()->enhance($sourceBytes, $prompt);

            $candidateKey = $this->candidateObjectKey($profile);
            $processed = $this->optimizer->processRawBytesAndStore($enhancedBytes, $disk, $candidateKey);

            try {
                $candidate = DB::transaction(function () use ($source, $run, $profile, $disk, $processed): MediaItem {
                    $candidate = MediaItem::query()->create([
                        'mediable_type' => $profile->getMorphClass(),
                        'mediable_id' => $profile->id,
                        'enhanced_from_media_id' => $source->id,
                        'media_type' => MediaItem::TYPE_PROFILE_PHOTO_ENHANCEMENT,
                        'storage_path_key' => $processed['path'],
                        'disk' => $disk,
                        'caption' => null,
                        'alt_text' => $source->alt_text,
                        'display_order' => 9999, // internal artifact — never ordered among customer photos
                        'is_primary' => false,
                        // Attribution follows the SOURCE uploader (the customer),
                        // never the approving administrator — the existing
                        // separation-of-duties guard stays fully intact.
                        'uploaded_by_id' => $source->uploaded_by_id,
                        'privacy' => MediaItem::PRIVACY_PRIVATE,
                        'review_status' => MediaItem::REVIEW_PENDING,
                        'mime_type' => $processed['mime_type'],
                        'size_bytes' => $processed['size_bytes'],
                        'photo_sha256' => $processed['sha256'],
                        'width' => $processed['width'],
                        'height' => $processed['height'],
                    ]);

                    $run->forceFill([
                        'candidate_media_id' => $candidate->id,
                        'status' => PhotoEnhancementRun::STATUS_COMPLETED,
                        'error_message' => null,
                        'finished_at' => now(),
                    ])->save();

                    return $candidate;
                });

                $this->audit->log(
                    action: 'media.photo_enhancement_candidate_created',
                    subject: $candidate,
                    after: [
                        'source_media_id' => $source->id,
                        'run_id' => $run->id,
                        'provider' => $run->provider,
                        'model' => $run->model,
                    ],
                );

                return $run->fresh() ?? $run;
            } catch (Throwable $e) {
                @Storage::disk($disk)->delete($processed['path']);
                throw $e;
            }
        } catch (Throwable $e) {
            $run->forceFill([
                'status' => PhotoEnhancementRun::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'finished_at' => now(),
            ])->save();

            $this->audit->log(
                action: 'media.photo_enhancement_failed',
                subject: $run,
                after: [
                    'source_media_id' => $run->source_media_id,
                    'error' => mb_substr($e->getMessage(), 0, 300),
                ],
            );

            return $run->fresh() ?? $run;
        }
    }

    /**
     * Accept Enhanced: the candidate converts into the active public
     * primary profile photo; the source stops being public/primary but is
     * retained (approved, private) for audit/fallback.
     */
    public function accept(MediaItem $candidate, User $admin): MediaItem
    {
        $this->assertDecidableCandidate($candidate, $admin);

        $source = $candidate->enhancedFrom;
        $run = $this->activeRunFor($source);
        if ($run === null || $run->candidate_media_id !== $candidate->id) {
            throw new ValueError('This enhancement candidate is not the active candidate for its source.');
        }

        return DB::transaction(function () use ($candidate, $source, $run, $admin): MediaItem {
            $profile = $candidate->mediable;
            if (! $profile instanceof Profile) {
                throw new ValueError('Candidate photograph owner is invalid.');
            }

            // Demote every other primary first (existing invariant: one primary).
            MediaItem::query()
                ->where('mediable_type', $profile->getMorphClass())
                ->where('mediable_id', $profile->id)
                ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
                ->where('is_primary', true)
                ->whereKeyNot($candidate->id)
                ->update(['is_primary' => false]);

            // Source: retained privately, no longer the public primary.
            $source->forceFill([
                'is_primary' => false,
                'privacy' => MediaItem::PRIVACY_PRIVATE,
            ])->save();

            // Candidate becomes a normal, approved, public, primary photo.
            $candidate = MediaItem::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            $candidate->forceFill([
                'media_type' => MediaItem::TYPE_PROFILE_PHOTO,
                'display_order' => $source->display_order,
                'review_status' => MediaItem::REVIEW_APPROVED,
                'privacy' => MediaItem::PRIVACY_PUBLIC,
                'is_primary' => true,
                'reviewed_by_user_id' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            $run->forceFill([
                'status' => PhotoEnhancementRun::STATUS_ACCEPTED,
                'decided_by_user_id' => $admin->id,
                'finished_at' => $run->finished_at ?? now(),
            ])->save();

            $this->audit->log(
                action: 'media.photo_enhancement_accepted',
                subject: $candidate,
                before: ['source_media_id' => $source->id, 'run_id' => $run->id],
                after: [
                    'source_media_id' => $source->id,
                    'source_review_status' => $source->review_status,
                    'source_privacy' => $source->privacy,
                    'reviewed_by' => $admin->id,
                ],
                actor: $admin,
            );

            return $candidate->fresh() ?? $candidate;
        });
    }

    /**
     * Keep Original: the optimized customer source becomes (or stays) the
     * approved/public/primary photo through the existing approval
     * semantics; the candidate is discarded with its storage object.
     */
    public function keepOriginal(MediaItem $candidate, User $admin): MediaItem
    {
        $this->assertDecidableCandidate($candidate, $admin);

        $source = $candidate->enhancedFrom;
        $run = $this->activeRunFor($source);
        if ($run === null || $run->candidate_media_id !== $candidate->id) {
            throw new ValueError('This enhancement candidate is not the active candidate for its source.');
        }

        return DB::transaction(function () use ($candidate, $source, $run, $admin): MediaItem {
            // Existing separation of duties applies to the source approval:
            // the admin must not have uploaded the customer photo themself.
            if ($source->uploaded_by_id !== null && (int) $source->uploaded_by_id === (int) $admin->id) {
                throw new ValueError('You uploaded this photograph; a different reviewer must decide.');
            }

            $profile = $source->mediable;
            if (! $profile instanceof Profile) {
                throw new ValueError('Photograph owner is invalid.');
            }

            MediaItem::query()
                ->where('mediable_type', $profile->getMorphClass())
                ->where('mediable_id', $profile->id)
                ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
                ->where('is_primary', true)
                ->whereKeyNot($source->id)
                ->update(['is_primary' => false]);

            $source->forceFill([
                'review_status' => MediaItem::REVIEW_APPROVED,
                'privacy' => MediaItem::PRIVACY_PUBLIC,
                'is_primary' => true,
                'reviewed_by_user_id' => $source->reviewed_by_user_id ?? $admin->id,
                'reviewed_at' => $source->reviewed_at ?? now(),
            ])->save();

            $this->discardCandidate($candidate, $run, PhotoEnhancementRun::STATUS_KEPT_ORIGINAL, $admin);

            $this->audit->log(
                action: 'media.photo_enhancement_original_kept',
                subject: $source,
                before: ['candidate_media_id' => $candidate->id],
                after: [
                    'run_id' => $run->id,
                    'candidate_discarded' => true,
                    'reviewed_by' => $admin->id,
                ],
                actor: $admin,
            );

            return $source->fresh() ?? $source;
        });
    }

    /**
     * Regenerate: discard the current candidate (row + storage object),
     * mark the run discarded, and start a fresh run from the same source.
     */
    public function regenerate(MediaItem $candidate, User $admin): ?PhotoEnhancementRun
    {
        $this->assertDecidableCandidate($candidate, $admin);

        $source = $candidate->enhancedFrom;
        $run = $this->activeRunFor($source);
        if ($run === null || $run->candidate_media_id !== $candidate->id) {
            throw new ValueError('This enhancement candidate is not the active candidate for its source.');
        }

        return DB::transaction(function () use ($candidate, $source, $run, $admin): ?PhotoEnhancementRun {
            $this->discardCandidate($candidate, $run, PhotoEnhancementRun::STATUS_DISCARDED, $admin);

            $this->audit->log(
                action: 'media.photo_enhancement_regenerated',
                subject: $source,
                after: ['previous_run_id' => $run->id, 'retry_of_candidate' => $candidate->id],
                actor: $admin,
            );

            return $this->dispatchForSource($source->fresh() ?? $source, $admin);
        });
    }

    /** Retry a failed run (bounded by max_retries). */
    public function retry(PhotoEnhancementRun $run, ?User $admin = null): ?PhotoEnhancementRun
    {
        if ($run->status !== PhotoEnhancementRun::STATUS_FAILED) {
            return $run;
        }

        $max = max(0, (int) config('jannayaks.ai.image_enhancement.max_retries', 3));
        if ((int) $run->retry_count >= $max) {
            return $run;
        }

        $run->forceFill([
            'status' => PhotoEnhancementRun::STATUS_QUEUED,
            'error_message' => null,
            'retry_count' => ((int) $run->retry_count) + 1,
            'started_at' => null,
            'finished_at' => null,
        ])->save();

        EnhanceProfilePhoto::dispatch($run->id);

        return $run->fresh() ?? $run;
    }

    private function cancel(PhotoEnhancementRun $run, string $reason): void
    {
        if (! $run->isActive()) {
            return;
        }

        $run->forceFill([
            'status' => PhotoEnhancementRun::STATUS_CANCELLED,
            'error_message' => $reason,
            'finished_at' => now(),
        ])->save();
    }

    /**
     * Delete the candidate row and its storage object, then terminal-mark
     * the run — no orphan rows/files.
     */
    private function discardCandidate(MediaItem $candidate, PhotoEnhancementRun $run, string $runStatus, User $actor): void
    {
        $disk = filled($candidate->disk) ? (string) $candidate->disk : 'public';
        $path = (string) $candidate->storage_path_key;

        $candidate->delete();

        try {
            if ($path !== '') {
                Storage::disk($disk)->delete($path);
            }
        } catch (Throwable) {
            // Row cleanup already committed; storage best-effort.
        }

        $run->forceFill([
            'status' => $runStatus,
            'candidate_media_id' => null,
            'decided_by_user_id' => $actor->id,
            'finished_at' => $run->finished_at ?? now(),
        ])->save();
    }

    /** @throws ValueError when the item is not a pending enhancement candidate */
    private function assertDecidableCandidate(MediaItem $candidate, User $admin): void
    {
        if (! $candidate->isEnhancementCandidate()) {
            throw new ValueError('This photograph is not an AI enhancement candidate.');
        }

        if ($candidate->review_status !== MediaItem::REVIEW_PENDING) {
            throw new ValueError('This enhancement candidate has already been decided.');
        }

        if ($candidate->uploaded_by_id !== null && (int) $candidate->uploaded_by_id === (int) $admin->id) {
            throw new ValueError('You uploaded the source photograph; a different reviewer must decide.');
        }

        if (! $candidate->enhancedFrom instanceof MediaItem) {
            throw new ValueError('The source photograph for this candidate no longer exists.');
        }
    }

    private function candidateObjectKey(Profile $profile): string
    {
        $prefix = trim((string) config('jannayaks.media.object_prefix', 'profile-media'), '/');

        return $prefix.'/profiles/'.$profile->id.'/enhanced-'.\Illuminate\Support\Str::lower((string) \Illuminate\Support\Str::ulid()).'.jpg';
    }

    /** Sources of a profile that currently have an active/failed run (admin table). */
    public function runStatusMapFor(Profile $profile): array
    {
        return PhotoEnhancementRun::query()
            ->whereHas('source', fn (Builder $q) => $q
                ->where('mediable_type', $profile->getMorphClass())
                ->where('mediable_id', $profile->id))
            ->orderByDesc('id')
            ->get()
            ->groupBy('source_media_id')
            ->map(fn ($runs) => $runs->first())
            ->all();
    }
}
