<?php

namespace App\Services;

use App\Models\InMemoriamProfile;
use App\Models\MediaItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class InMemoriamMediaService
{
    public function __construct(
        private StaffAuditLogger $audit,
        private ProfilePhotoOptimizer $optimizer,
        private InMemoriamLifecycleService $lifecycle,
    ) {}

    public function publicDisk(): string
    {
        return (string) config('jannayaks.media.public_disk', 'public');
    }

    public function photoSlotLimit(): int
    {
        return max(1, (int) config('jannayaks.tier_pricing.in_memoriam.photo_slots', 20));
    }

    /**
     * @throws ValidationException
     */
    public function uploadPhotograph(
        InMemoriamProfile $profile,
        UploadedFile $file,
        User $actor,
        ?string $altText = null,
        bool $makePrimary = false,
    ): MediaItem {
        if (! $actor->canManageEditorial()) {
            throw ValidationException::withMessages([
                'photo' => 'Only editorial staff may upload memorial photographs.',
            ]);
        }

        $this->lifecycle->assertEditableByStaff($profile, $actor);
        $this->assertValidImageUpload($file);

        $limit = $this->photoSlotLimit();
        $disk = $this->publicDisk();
        $objectKey = 'profile-media/in-memoriam/'.$profile->id.'/'.Str::lower((string) Str::ulid()).'.jpg';
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
                        'photo' => "This memorial allows a maximum of {$limit} photograph(s).",
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

                $media = MediaItem::query()->create([
                    'mediable_type' => $profile->getMorphClass(),
                    'mediable_id' => $profile->id,
                    'media_type' => MediaItem::TYPE_PROFILE_PHOTO,
                    'storage_path_key' => $processed['path'],
                    'disk' => $disk,
                    'alt_text' => $this->sanitizeAltText($altText),
                    'display_order' => max(1, $nextOrder),
                    'is_primary' => $shouldBePrimary,
                    'uploaded_by_id' => $actor->id,
                    'privacy' => MediaItem::PRIVACY_PRIVATE,
                    'review_status' => MediaItem::REVIEW_PENDING,
                    'mime_type' => $processed['mime_type'] ?? 'image/jpeg',
                    'size_bytes' => $processed['size_bytes'] ?? null,
                    'width' => $processed['width'] ?? null,
                    'height' => $processed['height'] ?? null,
                ]);

                $this->audit->log(
                    action: 'in_memoriam.media_uploaded',
                    subject: $media,
                    before: null,
                    after: [
                        'in_memoriam_profile_id' => $profile->id,
                        'review_status' => $media->review_status,
                        'is_primary' => $media->is_primary,
                    ],
                    actor: $actor,
                );

                return $media;
            });
        } catch (Throwable $e) {
            try {
                Storage::disk($disk)->delete($processed['path'] ?? $objectKey);
            } catch (Throwable) {
                //
            }

            throw $e;
        }
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

        $profile = $this->assertMemorialPhoto($media);

        // Pending review may complete after publication/seal; do not require memorial editability.
        if ($media->review_status !== MediaItem::REVIEW_PENDING) {
            throw ValidationException::withMessages([
                'media' => 'Only pending photographs can be approved.',
            ]);
        }

        return DB::transaction(function () use ($media, $reviewer, $note, $profile): MediaItem {
            $media = MediaItem::query()->whereKey($media->id)->lockForUpdate()->firstOrFail();
            $before = ['review_status' => $media->review_status, 'privacy' => $media->privacy];

            $media->forceFill([
                'review_status' => MediaItem::REVIEW_APPROVED,
                'privacy' => MediaItem::PRIVACY_PUBLIC,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $this->sanitizeNote($note),
            ])->save();

            $this->audit->log(
                action: 'in_memoriam.media_approved',
                subject: $media,
                before: $before,
                after: [
                    'review_status' => MediaItem::REVIEW_APPROVED,
                    'privacy' => MediaItem::PRIVACY_PUBLIC,
                    'in_memoriam_profile_id' => $profile->id,
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

        $profile = $this->assertMemorialPhoto($media);
        $this->lifecycle->assertEditableByStaff($profile, $reviewer);

        if ($media->review_status !== MediaItem::REVIEW_PENDING) {
            throw ValidationException::withMessages([
                'media' => 'Only pending photographs can be rejected.',
            ]);
        }

        return DB::transaction(function () use ($media, $reviewer, $note, $profile): MediaItem {
            $media = MediaItem::query()->whereKey($media->id)->lockForUpdate()->firstOrFail();
            $before = ['review_status' => $media->review_status, 'privacy' => $media->privacy];

            $media->forceFill([
                'review_status' => MediaItem::REVIEW_REJECTED,
                'privacy' => MediaItem::PRIVACY_PRIVATE,
                'is_primary' => false,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $this->sanitizeNote($note),
            ])->save();

            $this->audit->log(
                action: 'in_memoriam.media_rejected',
                subject: $media,
                before: $before,
                after: [
                    'review_status' => MediaItem::REVIEW_REJECTED,
                    'in_memoriam_profile_id' => $profile->id,
                ],
                actor: $reviewer,
            );

            return $media->fresh() ?? $media;
        });
    }

    /**
     * @return list<MediaItem>
     */
    public function approvedPublicPhotographs(InMemoriamProfile $profile): array
    {
        return $profile->media()
            ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
            ->where('review_status', MediaItem::REVIEW_APPROVED)
            ->where('privacy', MediaItem::PRIVACY_PUBLIC)
            ->orderByDesc('is_primary')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function primaryApprovedPhotograph(InMemoriamProfile $profile): ?MediaItem
    {
        $photos = $this->approvedPublicPhotographs($profile);
        foreach ($photos as $photo) {
            if ($photo->is_primary) {
                return $photo;
            }
        }

        return $photos[0] ?? null;
    }

    /**
     * @throws ValidationException
     */
    private function assertMemorialPhoto(MediaItem $media): InMemoriamProfile
    {
        if ($media->media_type !== MediaItem::TYPE_PROFILE_PHOTO) {
            throw ValidationException::withMessages([
                'media' => 'Only memorial photographs can be managed here.',
            ]);
        }

        $owner = $media->mediable;
        if (! $owner instanceof InMemoriamProfile) {
            throw ValidationException::withMessages([
                'media' => 'Photograph owner is invalid.',
            ]);
        }

        return $owner;
    }

    /**
     * @throws ValidationException
     */
    private function assertValidImageUpload(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['photo' => 'Uploaded file is not valid.']);
        }

        $maxKb = (int) config('jannayaks.media.max_upload_kb', 5120);
        if ($file->getSize() > ($maxKb * 1024)) {
            throw ValidationException::withMessages([
                'photo' => "Photographs must be {$maxKb} KB or smaller.",
            ]);
        }

        $ext = Str::lower((string) $file->getClientOriginalExtension());
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
        $clean = trim($note);

        return $clean === '' ? null : mb_substr($clean, 0, 1000);
    }
}
