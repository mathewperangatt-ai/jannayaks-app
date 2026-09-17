<?php

namespace App\Services;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialCustomerApproval;
use App\Models\EditorialRevisionRequest;
use App\Models\Profile;
use App\Models\User;
use App\Support\EditorialPlainTextSanitizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CustomerEditorialWorkflowService
{
    public function __construct(
        private StaffAuditLogger $auditLogger,
        private EditorialPlainTextSanitizer $sanitizer,
    ) {}

    public function includedRevisionLimit(): int
    {
        return max(0, (int) config('jannayaks.ai.editorial.included_prepublication_revision_rounds', 2));
    }

    public function remainingIncludedRevisionRounds(Application $application): int
    {
        return max(0, $this->includedRevisionLimit() - (int) $application->included_revision_rounds_used);
    }

    /**
     * Staff releases the current English (+ optional Malayalam) master for authenticated customer preview.
     */
    public function releaseForCustomerPreview(Application $application, User $actor): Application
    {
        if (! $actor->canManageEditorial()) {
            throw new InvalidArgumentException('Only editorial staff may release a customer preview.');
        }

        if ($application->package_tier === 'in_memoriam') {
            throw new InvalidArgumentException('In Memoriam applications are excluded from the living-profile customer preview workflow.');
        }

        if (! $application->isPaymentSettled() && $application->source_method !== 'admin_test_demo') {
            throw new InvalidArgumentException('Payment must be settled before customer preview.');
        }

        return DB::transaction(function () use ($application, $actor) {
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [
                Application::STATUS_PUBLISHED,
                Application::STATUS_CANCELLED,
                Application::STATUS_REFUNDED,
                Application::STATUS_ARCHIVED,
            ], true)) {
                throw new InvalidArgumentException('This application cannot enter customer preview from its current state.');
            }

            $profile = $this->requireProfile($locked);
            [$english, $malayalam] = $this->resolvePreviewVersions($profile);

            $this->invalidateActiveApproval(
                $locked,
                'Customer preview re-released with a new editorial version.',
                $actor,
            );

            $before = [
                'status' => $locked->status,
                'preview_english_editorial_content_id' => $locked->preview_english_editorial_content_id,
            ];

            $locked->forceFill([
                'status' => Application::STATUS_EDITORIAL_APPROVED,
                'customer_preview_released_at' => now(),
                'preview_english_editorial_content_id' => $english->id,
                'preview_malayalam_editorial_content_id' => $malayalam?->id,
                'customer_approved_at' => null,
                'customer_approved_english_editorial_content_id' => null,
                'customer_approved_by_user_id' => null,
            ])->save();

            if ($profile->status !== 'published') {
                $profile->forceFill(['status' => 'customer_preview'])->save();
            }

            EditorialRevisionRequest::query()
                ->where('application_id', $locked->id)
                ->whereIn('status', [
                    EditorialRevisionRequest::STATUS_SUBMITTED,
                    EditorialRevisionRequest::STATUS_IN_PROGRESS,
                ])
                ->update([
                    'status' => EditorialRevisionRequest::STATUS_COMPLETED,
                    'processed_by_user_id' => $actor->id,
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->auditLogger->log(
                action: 'editorial.customer_preview_released',
                subject: $locked,
                before: $before,
                after: [
                    'status' => $locked->status,
                    'preview_english_editorial_content_id' => $english->id,
                    'preview_malayalam_editorial_content_id' => $malayalam?->id,
                ],
                actor: $actor,
            );

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * @param  'revision'|'factual_correction'  $requestType
     */
    public function requestRevision(
        Application $application,
        User $member,
        string $requestText,
        string $requestType = EditorialRevisionRequest::TYPE_REVISION,
    ): EditorialRevisionRequest {
        $this->assertApplicationOwner($application, $member);

        if (! in_array($requestType, [
            EditorialRevisionRequest::TYPE_REVISION,
            EditorialRevisionRequest::TYPE_FACTUAL_CORRECTION,
        ], true)) {
            throw new InvalidArgumentException('Invalid revision request type.');
        }

        $text = $this->sanitizer->sanitizeBody($requestText);
        if ($text === '' || mb_strlen($text) < 5) {
            throw new InvalidArgumentException('Please describe the change you need.');
        }
        if (mb_strlen($text) > 5000) {
            throw new InvalidArgumentException('Revision request text is too long.');
        }

        $lock = Cache::lock('editorial-customer-revision:application:'.$application->id, 15);
        if (! $lock->get()) {
            throw new InvalidArgumentException('A revision request is already being processed. Please wait.');
        }

        try {
            return DB::transaction(function () use ($application, $member, $text, $requestType) {
                /** @var Application $locked */
                $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== Application::STATUS_EDITORIAL_APPROVED
                    || $locked->customer_preview_released_at === null
                    || $locked->preview_english_editorial_content_id === null) {
                    throw new InvalidArgumentException('Your profile is not currently available for revision requests.');
                }

                if ($locked->customer_approved_at !== null) {
                    throw new InvalidArgumentException('This profile has already been approved. Contact support if you need changes.');
                }

                $roundNumber = null;
                if ($requestType === EditorialRevisionRequest::TYPE_REVISION) {
                    if ($this->remainingIncludedRevisionRounds($locked) <= 0) {
                        throw new InvalidArgumentException('You have used both included revision rounds. Further meaningful changes after publication use a separate process.');
                    }
                    $roundNumber = (int) $locked->included_revision_rounds_used + 1;
                }

                $openExists = EditorialRevisionRequest::query()
                    ->where('application_id', $locked->id)
                    ->whereIn('status', [
                        EditorialRevisionRequest::STATUS_SUBMITTED,
                        EditorialRevisionRequest::STATUS_IN_PROGRESS,
                    ])
                    ->exists();
                if ($openExists) {
                    throw new InvalidArgumentException('You already have an open revision request. Please wait for the editorial team to respond.');
                }

                $revision = EditorialRevisionRequest::query()->create([
                    'application_id' => $locked->id,
                    'profile_id' => $locked->profile_id,
                    'requested_by_user_id' => $member->id,
                    'round_number' => $roundNumber,
                    'request_type' => $requestType,
                    'status' => EditorialRevisionRequest::STATUS_SUBMITTED,
                    'request_text' => $text,
                    'preview_english_editorial_content_id' => $locked->preview_english_editorial_content_id,
                    'preview_malayalam_editorial_content_id' => $locked->preview_malayalam_editorial_content_id,
                ]);

                $updates = [
                    'status' => Application::STATUS_EDITORIAL_REVISION_REQUESTED,
                ];
                if ($requestType === EditorialRevisionRequest::TYPE_REVISION) {
                    $updates['included_revision_rounds_used'] = $roundNumber;
                }

                $before = [
                    'status' => $locked->status,
                    'included_revision_rounds_used' => $locked->included_revision_rounds_used,
                ];
                $locked->forceFill($updates)->save();

                if ($locked->profile_id) {
                    Profile::query()->whereKey($locked->profile_id)->update([
                        'status' => 'revision_requested',
                        'updated_at' => now(),
                    ]);
                }

                $this->auditLogger->log(
                    action: 'editorial.customer_revision_requested',
                    subject: $revision,
                    before: $before,
                    after: [
                        'application_id' => $locked->id,
                        'request_type' => $requestType,
                        'round_number' => $roundNumber,
                        'included_revision_rounds_used' => $locked->fresh()?->included_revision_rounds_used,
                    ],
                    actor: $member,
                );

                return $revision->fresh() ?? $revision;
            });
        } finally {
            $lock->release();
        }
    }

    public function approvePreview(
        Application $application,
        User $member,
        int $englishEditorialContentId,
    ): EditorialCustomerApproval {
        $this->assertApplicationOwner($application, $member);

        $lock = Cache::lock('editorial-customer-approval:application:'.$application->id, 15);
        if (! $lock->get()) {
            throw new InvalidArgumentException('Approval is already being processed. Please wait.');
        }

        try {
            return DB::transaction(function () use ($application, $member, $englishEditorialContentId) {
                /** @var Application $locked */
                $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== Application::STATUS_EDITORIAL_APPROVED
                    || $locked->customer_preview_released_at === null) {
                    throw new InvalidArgumentException('Your profile is not ready for approval.');
                }

                if ((int) $locked->preview_english_editorial_content_id !== $englishEditorialContentId) {
                    throw new InvalidArgumentException('The profile has been updated. Please review the latest preview before approving.');
                }

                $english = EditorialContent::query()->find($englishEditorialContentId);
                if (! $english || (int) $english->profile_id !== (int) $locked->profile_id) {
                    throw new InvalidArgumentException('Invalid editorial version for approval.');
                }

                if ($locked->customer_approved_at !== null
                    && (int) $locked->customer_approved_english_editorial_content_id === $englishEditorialContentId) {
                    throw new InvalidArgumentException('This profile version is already approved.');
                }

                $this->invalidateActiveApproval($locked, 'Superseded by a new customer approval.', $member);

                $approval = EditorialCustomerApproval::query()->create([
                    'application_id' => $locked->id,
                    'profile_id' => $locked->profile_id,
                    'approved_by_user_id' => $member->id,
                    'english_editorial_content_id' => $english->id,
                    'malayalam_editorial_content_id' => $locked->preview_malayalam_editorial_content_id,
                    'approved_at' => now(),
                ]);

                $before = ['status' => $locked->status];
                $locked->forceFill([
                    'status' => Application::STATUS_AWAITING_PUBLICATION,
                    'customer_approved_at' => $approval->approved_at,
                    'customer_approved_english_editorial_content_id' => $english->id,
                    'customer_approved_by_user_id' => $member->id,
                ])->save();

                if ($locked->profile_id) {
                    Profile::query()->whereKey($locked->profile_id)->update([
                        'status' => 'member_approved',
                        'updated_at' => now(),
                    ]);
                }

                $this->auditLogger->log(
                    action: 'editorial.customer_approved',
                    subject: $approval,
                    before: $before,
                    after: [
                        'application_id' => $locked->id,
                        'english_editorial_content_id' => $english->id,
                        'application_status' => Application::STATUS_AWAITING_PUBLICATION,
                    ],
                    actor: $member,
                );

                return $approval->fresh() ?? $approval;
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Invalidate active customer approval when previewed/approved content changes.
     */
    public function invalidateApprovalForEditorialContent(
        EditorialContent $content,
        ?User $actor = null,
        string $reason = 'Editorial content changed after customer approval or preview.',
    ): void {
        $application = Application::query()
            ->where(function ($q) use ($content) {
                $q->where('preview_english_editorial_content_id', $content->id)
                    ->orWhere('preview_malayalam_editorial_content_id', $content->id)
                    ->orWhere('customer_approved_english_editorial_content_id', $content->id);
            })
            ->first();

        if (! $application) {
            return;
        }

        DB::transaction(function () use ($application, $actor, $reason, $content) {
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            $hadApproval = $locked->customer_approved_at !== null;
            $touchedPreview = (int) $locked->preview_english_editorial_content_id === (int) $content->id
                || (int) $locked->preview_malayalam_editorial_content_id === (int) $content->id
                || (int) $locked->customer_approved_english_editorial_content_id === (int) $content->id;

            $this->invalidateActiveApproval($locked, $reason, $actor);

            $updates = [
                'customer_approved_at' => null,
                'customer_approved_english_editorial_content_id' => null,
                'customer_approved_by_user_id' => null,
            ];

            // Content change after approval, while awaiting publication, or to the live preview version
            // requires staff to re-release preview and (if needed) renewed customer approval.
            if ($hadApproval
                || $touchedPreview
                || $locked->status === Application::STATUS_AWAITING_PUBLICATION) {
                if (in_array($locked->status, [
                    Application::STATUS_EDITORIAL_APPROVED,
                    Application::STATUS_AWAITING_PUBLICATION,
                    Application::STATUS_EDITORIAL_REVISION_REQUESTED,
                ], true)) {
                    $updates['status'] = Application::STATUS_IN_EDITORIAL_REVIEW;
                }
                $updates['customer_preview_released_at'] = null;
                $updates['preview_english_editorial_content_id'] = null;
                $updates['preview_malayalam_editorial_content_id'] = null;
            }

            $locked->forceFill($updates)->save();

            $this->auditLogger->log(
                action: 'editorial.customer_approval_invalidated',
                subject: $locked,
                after: [
                    'reason' => $reason,
                    'editorial_content_id' => $content->id,
                    'status' => $locked->status,
                ],
                actor: $actor,
            );
        });
    }

    public function memberFacingStatusLabel(Application $application): string
    {
        return match ($application->status) {
            Application::STATUS_AWAITING_EDITORIAL_REVIEW,
            Application::STATUS_IN_EDITORIAL_REVIEW => 'Your profile is being prepared',
            Application::STATUS_EDITORIAL_APPROVED => $application->customer_preview_released_at
                ? 'Your profile is ready for your review'
                : 'Your profile is being prepared',
            Application::STATUS_EDITORIAL_REVISION_REQUESTED => 'Revision requested',
            Application::STATUS_AWAITING_PUBLICATION => 'Profile approved — awaiting publication',
            Application::STATUS_PUBLISHED => 'Profile published',
            default => 'Application in progress',
        };
    }

    public function canMemberViewPreview(Application $application): bool
    {
        return $application->customer_preview_released_at !== null
            && $application->preview_english_editorial_content_id !== null
            && in_array($application->status, [
                Application::STATUS_EDITORIAL_APPROVED,
                Application::STATUS_EDITORIAL_REVISION_REQUESTED,
                Application::STATUS_AWAITING_PUBLICATION,
                Application::STATUS_PUBLISHED,
            ], true);
    }

    private function resolvePreviewVersions(Profile $profile): array
    {
        $english = EditorialContent::query()
            ->where('profile_id', $profile->id)
            ->where('language', EditorialContent::LANGUAGE_EN)
            ->where('status', EditorialContent::STATUS_APPROVED)
            ->orderByDesc('version_number')
            ->first();

        if (! $english) {
            $english = EditorialContent::query()
                ->where('profile_id', $profile->id)
                ->where('language', EditorialContent::LANGUAGE_EN)
                ->where('status', EditorialContent::STATUS_DRAFT)
                ->orderByDesc('version_number')
                ->first();
        }

        if (! $english || $english->isIncompleteFailedGeneration()) {
            throw new InvalidArgumentException('No usable English editorial draft is available to release.');
        }

        if ($english->status !== EditorialContent::STATUS_APPROVED) {
            throw new InvalidArgumentException('Approve the English editorial master before releasing customer preview.');
        }

        $malayalam = EditorialContent::query()
            ->where('profile_id', $profile->id)
            ->where('language', EditorialContent::LANGUAGE_ML)
            ->where('status', EditorialContent::STATUS_APPROVED)
            ->orderByDesc('version_number')
            ->first();

        return [$english, $malayalam];
    }

    private function invalidateActiveApproval(Application $application, string $reason, ?User $actor): void
    {
        EditorialCustomerApproval::query()
            ->where('application_id', $application->id)
            ->whereNull('invalidated_at')
            ->update([
                'invalidated_at' => now(),
                'invalidation_reason' => mb_substr($reason, 0, 255),
                'updated_at' => now(),
            ]);
    }

    private function requireProfile(Application $application): Profile
    {
        if (! $application->profile_id) {
            throw new InvalidArgumentException('Application has no linked profile for preview.');
        }

        $profile = Profile::query()->find($application->profile_id);
        if (! $profile) {
            throw new InvalidArgumentException('Linked profile was not found.');
        }

        return $profile;
    }

    private function assertApplicationOwner(Application $application, User $member): void
    {
        if ((int) $application->user_id !== (int) $member->id) {
            throw new InvalidArgumentException('This application is not yours.');
        }
    }
}
