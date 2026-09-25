<?php

namespace App\Services;

use App\Models\Application;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfileMediaService
{
    public function __construct(
        private StaffAuditLogger $audit,
        private ProfilePhotoOptimizer $optimizer,
    ) {}

    public function publicDisk(): string
    {
        return (string) config('jannayaks.media.public_disk', 'public');
    }

    public function photoSlotLimitForTier(string $tier): int
    {
        $packages = (array) config('jannayaks.tier_pricing.packages', []);
        $slots = (int) data_get($packages, $tier.'.photo_slots', 0);

        return max(0, $slots);
    }

    public function authoritativeTier(Profile $profile): ?string
    {
        $profile->loadMissing('application');
        $application = $profile->application;

        if (! $application instanceof Application) {
            return null;
        }

        $tier = (string) $application->package_tier;

        return in_array($tier, ['emerging', 'accomplished', 'distinguished'], true) ? $tier : null;
    }

    /**
     * Slot usage: pending + approved photographs. Rejected photos do not consume slots.
     */
    public function profilePhotoCount(Profile $profile): int
    {
        return $profile->media()
            ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
            ->whereIn('review_status', [MediaItem::REVIEW_PENDING, MediaItem::REVIEW_APPROVED])
            ->count();
    }

    /**
     * @throws ValidationException
     */
    public function uploadProfilePhoto(
        Profile $profile,
        UploadedFile $file,
        User $actor,
        ?string $altText = null,
        bool $makePrimary = false,
    ): MediaItem {
        $this->assertOwnedByMemberOrStaff($profile, $actor);
        $this->assertMemberMayMutateProfileContent($profile, $actor);

        $tier = $this->authoritativeTier($profile);
        if ($tier === null) {
            throw ValidationException::withMessages([
                'photo' => 'Profile package tier could not be determined.',
            ]);
        }

        $limit = $this->photoSlotLimitForTier($tier);
        if ($limit < 1) {
            throw ValidationException::withMessages([
                'photo' => 'This package does not include profile photographs.',
            ]);
        }

        $this->assertValidImageUpload($file);

        $disk = $this->publicDisk();
        $objectKey = $this->makeObjectKey($profile, 'jpg');

        // Optimize outside the DB transaction (CPU-bound); store only processed bytes.
        $processed = $this->optimizer->processAndStore($file, $disk, $objectKey);

        try {
            return DB::transaction(function () use ($profile, $actor, $altText, $makePrimary, $limit, $disk, $processed): MediaItem {
                $existing = MediaItem::query()
                    ->where('mediable_type', $profile->getMorphClass())
                    ->where('mediable_id', $profile->id)
                    ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
                    ->whereIn('review_status', [MediaItem::REVIEW_PENDING, MediaItem::REVIEW_APPROVED])
                    ->lockForUpdate()
                    ->get();

                if ($existing->count() >= $limit) {
                    throw ValidationException::withMessages([
                        'photo' => "This package allows a maximum of {$limit} photograph(s).",
                    ]);
                }

                $shouldBePrimary = $makePrimary || $existing->isEmpty();
                $nextOrder = ((int) $existing->max('display_order')) + 1;

                if ($shouldBePrimary) {
                    MediaItem::query()
                        ->where('mediable_type', $profile->getMorphClass())
                        ->where('mediable_id', $profile->id)
                        ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
                        ->where('is_primary', true)
                        ->update(['is_primary' => false]);
                }

                $item = MediaItem::query()->create([
                    'mediable_type' => $profile->getMorphClass(),
                    'mediable_id' => $profile->id,
                    'media_type' => MediaItem::TYPE_PROFILE_PHOTO,
                    'storage_path_key' => $processed['path'],
                    'disk' => $disk,
                    'caption' => null,
                    'alt_text' => $this->sanitizeAltText($altText),
                    'display_order' => max(1, $nextOrder),
                    'is_primary' => $shouldBePrimary,
                    'uploaded_by_id' => $actor->id,
                    // Never public until staff approval — even if requested as primary.
                    'privacy' => MediaItem::PRIVACY_PRIVATE,
                    'review_status' => MediaItem::REVIEW_PENDING,
                    'mime_type' => $processed['mime_type'],
                    'size_bytes' => $processed['size_bytes'],
                    'photo_sha256' => $processed['sha256'],
                    'width' => $processed['width'],
                    'height' => $processed['height'],
                ]);

                $this->audit->log(
                    action: 'media.profile_photo_uploaded',
                    subject: $item,
                    before: null,
                    after: [
                        'profile_id' => $profile->id,
                        'media_id' => $item->id,
                        'is_primary' => $item->is_primary,
                        'review_status' => $item->review_status,
                        'privacy' => $item->privacy,
                        'disk' => $item->disk,
                        'mime_type' => $item->mime_type,
                        'size_bytes' => $item->size_bytes,
                        'width' => $item->width,
                        'height' => $item->height,
                    ],
                    actor: $actor,
                );

                return $item;
            });
        } catch (Throwable $e) {
            try {
                Storage::disk($disk)->delete($processed['path']);
            } catch (Throwable) {
                //
            }
            throw $e;
        }
    }

    /**
     * Member/staff preference only. Does not make an unapproved photo publicly visible.
     *
     * @throws ValidationException
     */
    public function setPrimary(Profile $profile, MediaItem $media, User $actor): MediaItem
    {
        $this->assertOwnedByMemberOrStaff($profile, $actor);
        $this->assertMemberMayMutateProfileContent($profile, $actor);
        $this->assertMediaBelongsToProfile($profile, $media);

        if ($media->media_type !== MediaItem::TYPE_PROFILE_PHOTO) {
            throw ValidationException::withMessages([
                'media' => 'Only profile photographs can be primary.',
            ]);
        }

        if ($media->review_status === MediaItem::REVIEW_REJECTED) {
            throw ValidationException::withMessages([
                'media' => 'Rejected photographs cannot be set as primary.',
            ]);
        }

        return DB::transaction(function () use ($profile, $media, $actor): MediaItem {
            MediaItem::query()
                ->where('mediable_type', $profile->getMorphClass())
                ->where('mediable_id', $profile->id)
                ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
                ->lockForUpdate()
                ->get();

            MediaItem::query()
                ->where('mediable_type', $profile->getMorphClass())
                ->where('mediable_id', $profile->id)
                ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
                ->where('is_primary', true)
                ->whereKeyNot($media->id)
                ->update(['is_primary' => false]);

            $before = ['is_primary' => (bool) $media->is_primary];
            $media->forceFill(['is_primary' => true])->save();

            $this->audit->log(
                action: 'media.profile_photo_primary_set',
                subject: $media,
                before: $before,
                after: [
                    'is_primary' => true,
                    'profile_id' => $profile->id,
                    'review_status' => $media->review_status,
                ],
                actor: $actor,
            );

            return $media->fresh() ?? $media;
        });
    }

    /**
     * @throws ValidationException
     */
    public function approve(MediaItem $media, User $reviewer, ?string $note = null): MediaItem
    {
        if (! $reviewer->canManageEditorial()) {
            throw ValidationException::withMessages([
                'media' => 'You are not allowed to approve photographs.',
            ]);
        }

        // Separation of duties: the uploader of a photograph can never approve it.
        // Fail closed when uploader identity is missing (legacy rows after user
        // deletion) — such photographs require an administrator to resolve.
        if ($media->uploaded_by_id === null) {
            throw ValidationException::withMessages([
                'media' => 'This photograph has no recorded uploader and cannot be approved. Contact an administrator.',
            ]);
        }

        if ((int) $media->uploaded_by_id === (int) $reviewer->id) {
            $this->audit->log(
                action: 'media.profile_photo_approval_denied',
                subject: $media,
                before: ['review_status' => $media->review_status],
                after: ['reason' => 'uploader_cannot_approve_own_upload'],
                actor: $reviewer,
            );

            throw ValidationException::withMessages([
                'media' => 'A photograph cannot be approved by the person who uploaded it. A different reviewer must approve it.',
            ]);
        }

        if ($media->media_type !== MediaItem::TYPE_PROFILE_PHOTO) {
            throw ValidationException::withMessages([
                'media' => 'Only profile photographs can be approved here.',
            ]);
        }

        if ($media->review_status !== MediaItem::REVIEW_PENDING) {
            throw ValidationException::withMessages([
                'media' => 'Only pending photographs can be approved.',
            ]);
        }

        $profile = $media->mediable;
        if (! $profile instanceof Profile) {
            throw ValidationException::withMessages([
                'media' => 'Photograph owner is invalid.',
            ]);
        }

        return DB::transaction(function () use ($media, $reviewer, $note, $profile): MediaItem {
            $media = MediaItem::query()->whereKey($media->id)->lockForUpdate()->firstOrFail();
            $before = [
                'review_status' => $media->review_status,
                'privacy' => $media->privacy,
                'is_primary' => $media->is_primary,
            ];

            $media->forceFill([
                'review_status' => MediaItem::REVIEW_APPROVED,
                'privacy' => MediaItem::PRIVACY_PUBLIC,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $this->sanitizeNote($note),
            ])->save();

            // If this photo is the member-requested primary, ensure uniqueness among approved set.
            if ($media->is_primary) {
                MediaItem::query()
                    ->where('mediable_type', $profile->getMorphClass())
                    ->where('mediable_id', $profile->id)
                    ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
                    ->where('is_primary', true)
                    ->whereKeyNot($media->id)
                    ->update(['is_primary' => false]);
            }

            $this->audit->log(
                action: 'media.profile_photo_approved',
                subject: $media,
                before: $before,
                after: [
                    'review_status' => MediaItem::REVIEW_APPROVED,
                    'privacy' => MediaItem::PRIVACY_PUBLIC,
                    'is_primary' => $media->is_primary,
                    'profile_id' => $profile->id,
                ],
                actor: $reviewer,
            );

            return $media->fresh() ?? $media;
        });
    }

    /**
     * @throws ValidationException
     */
    public function reject(MediaItem $media, User $reviewer, ?string $note = null): MediaItem
    {
        if (! $reviewer->canManageEditorial()) {
            throw ValidationException::withMessages([
                'media' => 'You are not allowed to reject photographs.',
            ]);
        }

        if ($media->media_type !== MediaItem::TYPE_PROFILE_PHOTO) {
            throw ValidationException::withMessages([
                'media' => 'Only profile photographs can be rejected here.',
            ]);
        }

        if ($media->review_status !== MediaItem::REVIEW_PENDING) {
            throw ValidationException::withMessages([
                'media' => 'Only pending photographs can be rejected.',
            ]);
        }

        $profile = $media->mediable;
        if (! $profile instanceof Profile) {
            throw ValidationException::withMessages([
                'media' => 'Photograph owner is invalid.',
            ]);
        }

        return DB::transaction(function () use ($media, $reviewer, $note, $profile): MediaItem {
            $media = MediaItem::query()->whereKey($media->id)->lockForUpdate()->firstOrFail();
            $wasPrimary = (bool) $media->is_primary;
            $before = [
                'review_status' => $media->review_status,
                'privacy' => $media->privacy,
                'is_primary' => $wasPrimary,
            ];

            $media->forceFill([
                'review_status' => MediaItem::REVIEW_REJECTED,
                'privacy' => MediaItem::PRIVACY_PRIVATE,
                'is_primary' => false,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $this->sanitizeNote($note),
            ])->save();

            if ($wasPrimary) {
                $next = MediaItem::query()
                    ->where('mediable_type', $profile->getMorphClass())
                    ->where('mediable_id', $profile->id)
                    ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
                    ->whereIn('review_status', [MediaItem::REVIEW_PENDING, MediaItem::REVIEW_APPROVED])
                    ->orderByRaw("CASE review_status WHEN 'approved' THEN 0 WHEN 'pending_review' THEN 1 ELSE 2 END")
                    ->orderBy('display_order')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($next) {
                    $next->forceFill(['is_primary' => true])->save();
                }
            }

            $this->audit->log(
                action: 'media.profile_photo_rejected',
                subject: $media,
                before: $before,
                after: [
                    'review_status' => MediaItem::REVIEW_REJECTED,
                    'privacy' => MediaItem::PRIVACY_PRIVATE,
                    'is_primary' => false,
                    'profile_id' => $profile->id,
                ],
                actor: $reviewer,
            );

            return $media->fresh() ?? $media;
        });
    }

    /**
     * @throws ValidationException
     */
    public function deletePhoto(Profile $profile, MediaItem $media, User $actor): void
    {
        $this->assertOwnedByMemberOrStaff($profile, $actor);
        $this->assertMemberMayMutateProfileContent($profile, $actor);
        $this->assertMediaBelongsToProfile($profile, $media);

        if ($media->media_type !== MediaItem::TYPE_PROFILE_PHOTO) {
            throw ValidationException::withMessages([
                'media' => 'Only profile photographs can be removed here.',
            ]);
        }

        // Members may not delete a currently public approved photo that is the sole live portrait
        // without staff — they may remove pending/rejected freely. Staff may delete any.
        if (! $actor->canManageEditorial()
            && $media->review_status === MediaItem::REVIEW_APPROVED
            && $media->privacy === MediaItem::PRIVACY_PUBLIC
            && $profile->isPubliclyListed()) {
            $approvedCount = MediaItem::query()
                ->where('mediable_type', $profile->getMorphClass())
                ->where('mediable_id', $profile->id)
                ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
                ->where('review_status', MediaItem::REVIEW_APPROVED)
                ->count();

            if ($approvedCount <= 1) {
                throw ValidationException::withMessages([
                    'media' => 'The live public photograph cannot be removed until a replacement is approved.',
                ]);
            }
        }

        DB::transaction(function () use ($profile, $media, $actor): void {
            $wasPrimary = (bool) $media->is_primary;
            $disk = filled($media->disk) ? (string) $media->disk : $this->publicDisk();
            $path = (string) $media->storage_path_key;

            $this->audit->log(
                action: 'media.profile_photo_deleted',
                subject: $media,
                before: [
                    'profile_id' => $profile->id,
                    'media_id' => $media->id,
                    'is_primary' => $wasPrimary,
                    'review_status' => $media->review_status,
                    'disk' => $disk,
                ],
                after: null,
                actor: $actor,
            );

            $media->delete();

            if ($wasPrimary) {
                $next = MediaItem::query()
                    ->where('mediable_type', $profile->getMorphClass())
                    ->where('mediable_id', $profile->id)
                    ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
                    ->whereIn('review_status', [MediaItem::REVIEW_PENDING, MediaItem::REVIEW_APPROVED])
                    ->orderByRaw("CASE review_status WHEN 'approved' THEN 0 WHEN 'pending_review' THEN 1 ELSE 2 END")
                    ->orderBy('display_order')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($next) {
                    $next->forceFill(['is_primary' => true])->save();
                }
            }

            if ($path !== '') {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (Throwable) {
                    //
                }
            }
        });
    }

    /**
     * @throws ValidationException
     */
    private function assertValidImageUpload(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'photo' => 'Uploaded file is not valid.',
            ]);
        }

        $maxKb = (int) config('jannayaks.media.max_upload_kb', 5120);
        if ($file->getSize() > ($maxKb * 1024)) {
            throw ValidationException::withMessages([
                'photo' => "Photographs must be {$maxKb} KB or smaller.",
            ]);
        }

        $ext = Str::lower((string) $file->getClientOriginalExtension());
        $forbidden = array_map('strtolower', (array) config('jannayaks.media.forbidden_extensions', []));
        if ($ext !== '' && in_array($ext, $forbidden, true)) {
            throw ValidationException::withMessages([
                'photo' => 'This file type is not allowed.',
            ]);
        }

        $allowedExt = array_map('strtolower', (array) config('jannayaks.media.allowed_extensions', []));
        if ($ext === '' || ! in_array($ext, $allowedExt, true)) {
            throw ValidationException::withMessages([
                'photo' => 'Only JPEG, PNG, or WebP photographs are allowed.',
            ]);
        }

        $mime = (string) ($file->getMimeType() ?: '');
        $allowedMimes = (array) config('jannayaks.media.allowed_mime_types', []);
        if (! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'photo' => 'Only JPEG, PNG, or WebP photographs are allowed.',
            ]);
        }

        $path = $file->getRealPath();
        if (! is_string($path) || $path === '' || @getimagesize($path) === false) {
            throw ValidationException::withMessages([
                'photo' => 'The uploaded file is not a valid image.',
            ]);
        }
    }

    private function makeObjectKey(Profile $profile, string $extension): string
    {
        $prefix = trim((string) config('jannayaks.media.object_prefix', 'profile-media'), '/');

        return $prefix.'/profiles/'.$profile->id.'/'.Str::lower((string) Str::ulid()).'.'.$extension;
    }

    private function sanitizeAltText(?string $altText): ?string
    {
        if ($altText === null) {
            return null;
        }

        $clean = trim(strip_tags($altText));

        return $clean === '' ? null : Str::limit($clean, 255, '');
    }

    private function sanitizeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $clean = trim(strip_tags($note));

        return $clean === '' ? null : Str::limit($clean, 2000, '');
    }

    private function assertMediaBelongsToProfile(Profile $profile, MediaItem $media): void
    {
        if ((string) $media->mediable_type !== $profile->getMorphClass()
            || (int) $media->mediable_id !== (int) $profile->id) {
            throw ValidationException::withMessages([
                'media' => 'This media item does not belong to the profile.',
            ]);
        }
    }

    private function assertOwnedByMemberOrStaff(Profile $profile, User $actor): void
    {
        if ($actor->canManageEditorial()) {
            return;
        }

        if ((int) $profile->user_id === (int) $actor->id) {
            return;
        }

        throw ValidationException::withMessages([
            'media' => 'You are not allowed to manage this profile media.',
        ]);
    }

    /**
     * After final customer approval / publication, members may not directly alter photos.
     * Editorial staff retain authorized correction paths.
     */
    private function assertMemberMayMutateProfileContent(Profile $profile, User $actor): void
    {
        if ($actor->canManageEditorial()) {
            return;
        }

        $profile->loadMissing('application');
        $application = $profile->application;

        if ($application instanceof Application && $application->memberDirectEditsLocked()) {
            throw ValidationException::withMessages([
                'photo' => 'This profile has been approved. Photograph changes must be handled by Jannayaks editorial staff.',
            ]);
        }

        if ($profile->status === 'published' || $profile->published_at !== null) {
            throw ValidationException::withMessages([
                'photo' => 'Published profile photographs cannot be changed directly. Contact Jannayaks for an editorial correction.',
            ]);
        }
    }
}
