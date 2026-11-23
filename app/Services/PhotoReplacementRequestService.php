<?php

namespace App\Services;

use App\Mail\PhotoReplacementDecisionMail;
use App\Models\MediaItem;
use App\Models\PendingPhotoReplacement;
use App\Models\Profile;
use App\Models\User;
use App\Security\PhotoSafetyScreeningService;
use App\Security\PublishedProfileAuthorizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Member-submitted photograph replacement workflow (Wave 3).
 *
 * submit():    a member of a published profile uploads a replacement
 *              photograph. It is stored SEPARATELY on the private disk — the
 *              published photographs are untouched until explicit approval —
 *              and automatically screened by the image-safety screen.
 * approve():   Security Administrator authority (transitional legacy-admin
 *              override until strict separation). Flagged submissions require
 *              explicit manual-examination confirmation. Publication runs the
 *              EXISTING authorised media workflow (upload → approve → primary)
 *              so the audit trail records the actor and the trusted integrity
 *              baseline refreshes in the normal way.
 * reject():    Security Administrator authority; zero effect on published
 *              content.
 *
 * The submitting member is notified of every decision.
 */
class PhotoReplacementRequestService
{
    public function __construct(
        private PublishedProfileAuthorizer $publishedProfiles,
        private PhotoSafetyScreeningService $screening,
        private ProfileMediaService $media,
        private StaffAuditLogger $audit,
    ) {}

    public function submit(Profile $profile, User $member, UploadedFile $file, ?string $note = null): PendingPhotoReplacement
    {
        // Ownership: only the profile's own member account may submit.
        if ((int) $profile->user_id !== (int) $member->id) {
            throw new InvalidArgumentException('You can only submit replacements for your own profile.');
        }

        if ($profile->status !== 'published' || $profile->published_at === null) {
            throw new InvalidArgumentException('Photograph replacement is available after the profile is published.');
        }

        if ($file->getError() !== UPLOAD_ERR_OK || ! $file->isValid()) {
            throw new InvalidArgumentException('The uploaded file did not transfer correctly. Please try again.');
        }

        return DB::transaction(function () use ($profile, $member, $file, $note): PendingPhotoReplacement {
            $request = PendingPhotoReplacement::query()->create([
                'profile_id' => $profile->id,
                'application_id' => $profile->application?->id,
                'member_user_id' => $member->id,
                'storage_path' => '',
                'mime_type' => (string) $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'sha256' => '',
                'member_note' => mb_substr(trim((string) $note), 0, 1000),
                'status' => PendingPhotoReplacement::STATUS_SUBMITTED,
            ]);

            $path = 'photo-replacements/'.$request->id.'/original.'.$file->extension();
            Storage::disk('private_uploads')->put($path, file_get_contents($file->getRealPath()));
            $absolute = Storage::disk('private_uploads')->path($path);

            $screening = $this->screening->screen($absolute, (string) $file->getMimeType(), (int) $file->getSize());

            $request->forceFill([
                'storage_path' => $path,
                'sha256' => hash_file('sha256', $absolute),
                'width' => $screening['width'],
                'height' => $screening['height'],
                'screening_result' => $screening['verdict'],
                'screening_reasons' => $screening['reasons'],
            ])->save();

            $this->audit->log(
                action: 'photo_replacement.submitted',
                subject: $profile,
                after: [
                    'request_id' => $request->id,
                    'member_user_id' => $member->id,
                    'screening_result' => $screening['verdict'],
                ],
                actor: $member,
            );

            return $request;
        });
    }

    public function approve(
        PendingPhotoReplacement $request,
        User $actor,
        string $reviewNote = '',
        bool $confirmManualExamination = false,
    ): PendingPhotoReplacement {
        // Exclusive Security Administrator authority (no alternative route).
        $this->publishedProfiles->authorize($actor, 'photo_replacement.approve');

        if ($request->status !== PendingPhotoReplacement::STATUS_SUBMITTED) {
            throw new InvalidArgumentException('This request has already been reviewed.');
        }
        if (! Storage::disk('private_uploads')->exists($request->storage_path)) {
            throw new InvalidArgumentException('The submitted file is no longer available — ask the member to resubmit.');
        }
        if ($request->screening_result === PendingPhotoReplacement::SCREENING_FLAGGED && ! $confirmManualExamination) {
            throw new InvalidArgumentException(
                'This submission is flagged by image-safety screening. Examine it manually and confirm the examination before approving.',
            );
        }

        $profile = $request->profile;
        $member = $request->member;

        return DB::transaction(function () use ($request, $profile, $member, $actor, $reviewNote, $confirmManualExamination): PendingPhotoReplacement {
            $lockedRequest = PendingPhotoReplacement::query()->whereKey($request->id)->lockForUpdate()->first() ?? $request;
            if ($lockedRequest->status !== PendingPhotoReplacement::STATUS_SUBMITTED) {
                throw new InvalidArgumentException('This request has already been reviewed.');
            }

            // Identify the current primary photograph being replaced.
            $oldPrimary = MediaItem::query()
                ->where('mediable_type', $profile->getMorphClass())
                ->where('mediable_id', $profile->id)
                ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
                ->where('is_primary', true)
                ->first();

            // Publish through the EXISTING authorised media workflow. The
            // Security Administrator is the operator-approver of this audited
            // maintenance replacement (the member remains the submitter of
            // record via the request row and audit trail). The integrity
            // baseline refreshes through the normal workflow path.
            $uploaded = $this->media->uploadProfilePhoto(
                profile: $profile->fresh() ?? $profile,
                file: new \Illuminate\Http\UploadedFile(
                    Storage::disk('private_uploads')->path($lockedRequest->storage_path),
                    'photo-replacement-'.$lockedRequest->id.'.jpg',
                    $lockedRequest->mime_type,
                    null,
                    true,
                ),
                actor: $actor,
                altText: 'Portrait of '.($profile->display_name ?? 'Jannayaks profile').' (approved member replacement)',
                makePrimary: true,
            );
            $this->media->approve($uploaded->fresh() ?? $uploaded, $actor, 'Approved member photograph replacement (request #'.$lockedRequest->id.').');
            if (! ($uploaded->fresh()?->is_primary ?? false)) {
                $this->media->setPrimary($profile->fresh() ?? $profile, $uploaded->fresh() ?? $uploaded, $actor);
            }

            // Retire the replaced primary once its replacement is live. The
            // media service permits this now: the retired portrait is no
            // longer the sole live photograph.
            if ($oldPrimary instanceof MediaItem && (int) $oldPrimary->id !== (int) $uploaded->id) {
                $this->media->deletePhoto($profile->fresh() ?? $profile, $oldPrimary->fresh() ?? $oldPrimary, $actor);
            }

            $lockedRequest->forceFill([
                'status' => PendingPhotoReplacement::STATUS_APPROVED,
                'review_note' => mb_substr(trim($reviewNote), 0, 1000),
                'reviewer_user_id' => $actor->id,
                'manual_examination_confirmed' => $confirmManualExamination || $lockedRequest->screening_result === PendingPhotoReplacement::SCREENING_CLEAR,
                'examined_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'published_at' => now(),
            ])->save();

            $this->audit->log(
                action: 'photo_replacement.approved',
                subject: $profile,
                before: ['primary_photo_media_id' => $oldPrimary?->id],
                after: [
                    'request_id' => $lockedRequest->id,
                    'new_media_id' => $uploaded->id,
                    'reviewer_user_id' => $actor->id,
                ],
                actor: $actor,
            );

            $this->notifyMember($lockedRequest, true);

            return $lockedRequest;
        });
    }

    public function reject(PendingPhotoReplacement $request, User $actor, string $reviewNote = ''): PendingPhotoReplacement
    {
        $this->publishedProfiles->authorize($actor, 'photo_replacement.reject');

        if ($request->status !== PendingPhotoReplacement::STATUS_SUBMITTED) {
            throw new InvalidArgumentException('This request has already been reviewed.');
        }

        $request->forceFill([
            'status' => PendingPhotoReplacement::STATUS_REJECTED,
            'review_note' => mb_substr(trim($reviewNote), 0, 1000),
            'reviewer_user_id' => $actor->id,
            'reviewed_at' => now(),
        ])->save();

        $this->audit->log(
            action: 'photo_replacement.rejected',
            subject: $request->profile,
            after: ['request_id' => $request->id, 'reviewer_user_id' => $actor->id],
            actor: $actor,
        );

        $this->notifyMember($request, false);

        return $request;
    }

    private function notifyMember(PendingPhotoReplacement $request, bool $approved): void
    {
        $member = $request->member;
        if ($member === null) {
            return;
        }

        try {
            Mail::to($member->email)->send(new PhotoReplacementDecisionMail($request, $approved));
        } catch (Throwable $e) {
            // Notification failure must never roll back the review decision.
            Log::warning('photo-replacement: member notification failed', [
                'request_id' => $request->id,
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
        }
    }
}
