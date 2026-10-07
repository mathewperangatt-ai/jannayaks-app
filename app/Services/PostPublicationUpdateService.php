<?php

namespace App\Services;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialRevisionRequest;
use App\Models\Profile;
use App\Models\User;
use App\Support\EditorialPlainTextSanitizer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Post-publication profile maintenance (Pass 1 foundation).
 *
 * A published living profile entitles the customer to ONE complimentary
 * bundled profile update per 3-month cycle. The cycle is anchored to the
 * profile's actual publication date and recurs every 3 calendar months
 * (published 1 January → eligible 1 January, 1 April, 1 July, 1 October…).
 * Using an update never moves the anchor. A request submitted outside an
 * unused complimentary window is classified as a paid-update opportunity.
 *
 * Classification is snapshotted onto the request at submission so historical
 * records never change meaning as the calendar advances.
 */
class PostPublicationUpdateService
{
    private const CYCLE_MONTHS = 3;

    public function __construct(
        private StaffAuditLogger $auditLogger,
        private EditorialPlainTextSanitizer $sanitizer,
    ) {}

    /**
     * Eligibility state for a published profile as of $now.
     *
     * @return array{eligible: bool, cycle_start: CarbonInterface, cycle_end: CarbonInterface, next_eligible_on: CarbonInterface, reason: string}
     */
    public function eligibilityFor(Profile $profile, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $anchor = $profile->published_at->copy()->startOfDay();
        $today = $now->copy()->startOfDay();

        $elapsed = max(0, (int) floor($anchor->diffInMonths($today)));
        $cycleStart = $anchor->copy()->addMonths(intdiv($elapsed, self::CYCLE_MONTHS) * self::CYCLE_MONTHS);
        $cycleEnd = $cycleStart->copy()->addMonths(self::CYCLE_MONTHS); // exclusive

        $used = EditorialRevisionRequest::query()
            ->where('application_id', $profile->application?->id)
            ->where('request_type', EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE)
            ->where('billing_classification', EditorialRevisionRequest::BILLING_COMPLIMENTARY)
            ->where('created_at', '>=', $cycleStart)
            ->where('created_at', '<', $cycleEnd)
            ->exists();

        return [
            'eligible' => ! $used,
            'cycle_start' => $cycleStart,
            'cycle_end' => $cycleEnd,
            'next_eligible_on' => $cycleEnd->copy()->startOfDay(),
            'reason' => $used
                ? 'The complimentary update for this cycle has already been used.'
                : 'Complimentary window currently open.',
        ];
    }

    /**
     * Submit a bundled maintenance request for a published profile.
     */
    public function submit(Application $application, User $member, string $requestText): EditorialRevisionRequest
    {
        $this->assertApplicationOwner($application, $member);

        $text = $this->sanitizer->sanitizeBody($requestText);
        if (mb_strlen($text) < 5) {
            throw new InvalidArgumentException('Please describe the changes you need.');
        }
        if (mb_strlen($text) > 5000) {
            throw new InvalidArgumentException('Update request text is too long.');
        }

        $lock = Cache::lock('published-profile-update:application:'.$application->id, 15);
        if (! $lock->get()) {
            throw new InvalidArgumentException('An update request is already being processed. Please wait.');
        }

        try {
            return DB::transaction(function () use ($application, $member, $text) {
                /** @var Application $locked */
                $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== Application::STATUS_PUBLISHED || $locked->profile_id === null) {
                    throw new InvalidArgumentException('Post-publication updates are only available for published profiles.');
                }

                $profile = Profile::query()->whereKey($locked->profile_id)->lockForUpdate()->firstOrFail();
                if (! $profile->isPubliclyListed() || $profile->published_at === null) {
                    throw new InvalidArgumentException('Post-publication updates are only available for published profiles.');
                }

                // A6 — use the SAME open-status set as openRequestFor so a
                // request in customer_preview/customer_approved also blocks a
                // second submission (which would orphan the first).
                $openExists = $this->openRequestFor($locked) !== null;
                if ($openExists) {
                    throw new InvalidArgumentException('You already have an open update request. Please wait for the editorial team to respond.');
                }

                $eligibility = $this->eligibilityFor($profile);
                $classification = $eligibility['eligible']
                    ? EditorialRevisionRequest::BILLING_COMPLIMENTARY
                    : EditorialRevisionRequest::BILLING_PAID;

                $request = EditorialRevisionRequest::query()->create([
                    'application_id' => $locked->id,
                    'profile_id' => $locked->profile_id,
                    'requested_by_user_id' => $member->id,
                    'round_number' => null,
                    'request_type' => EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE,
                    'billing_classification' => $classification,
                    'eligibility_published_on' => $profile->published_at->copy()->startOfDay()->toDateString(),
                    'next_eligible_on' => $eligibility['next_eligible_on']->toDateString(),
                    'status' => EditorialRevisionRequest::STATUS_SUBMITTED,
                    'request_text' => $text,
                    'preview_english_editorial_content_id' => $locked->published_english_editorial_content_id,
                    'preview_malayalam_editorial_content_id' => $locked->published_malayalam_editorial_content_id,
                ]);

                $this->auditLogger->log(
                    action: 'editorial.published_update_requested',
                    subject: $request,
                    after: [
                        'application_id' => $locked->id,
                        'billing_classification' => $classification,
                        'eligibility_published_on' => $request->eligibility_published_on?->toDateString(),
                        'next_eligible_on' => $request->next_eligible_on?->toDateString(),
                    ],
                    actor: $member,
                );

                return $request;
            });
        } finally {
            $lock->release();
        }
    }

    private function assertApplicationOwner(Application $application, User $member): void
    {
        if ((int) $application->user_id !== (int) $member->id) {
            throw new InvalidArgumentException('This application is not yours.');
        }
    }

    /**
     * The open (not yet published/cancelled) maintenance request for an
     * application, if any.
     */
    public function openRequestFor(Application $application): ?EditorialRevisionRequest
    {
        return EditorialRevisionRequest::query()
            ->where('application_id', $application->id)
            ->where('request_type', EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE)
            ->whereIn('status', [
                EditorialRevisionRequest::STATUS_SUBMITTED,
                EditorialRevisionRequest::STATUS_IN_PROGRESS,
                EditorialRevisionRequest::STATUS_CUSTOMER_PREVIEW,
                EditorialRevisionRequest::STATUS_CUSTOMER_APPROVED,
            ])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Editorial preparation: generate the AI draft for the open maintenance
     * request through the existing generation pipeline and link the result.
     */
    public function prepareViaAiDraft(Application $application, User $actor): EditorialRevisionRequest
    {
        if (! $actor->canManageEditorial()) {
            throw new InvalidArgumentException('Only editorial staff may prepare maintenance drafts.');
        }

        $request = $this->openRequestFor($application);
        if ($request === null || ! in_array($request->status, [EditorialRevisionRequest::STATUS_SUBMITTED, EditorialRevisionRequest::STATUS_IN_PROGRESS], true)) {
            throw new InvalidArgumentException('No open maintenance request is awaiting editorial preparation.');
        }

        $run = app(\App\Services\EditorialGenerationService::class)->generateForApplication($application, $actor);
        $run->refresh();

        $request->forceFill([
            'resulting_english_editorial_content_id' => $run->english_editorial_content_id,
            'resulting_malayalam_editorial_content_id' => $run->malayalam_editorial_content_id,
            'status' => EditorialRevisionRequest::STATUS_IN_PROGRESS,
        ])->save();

        $this->auditLogger->log(
            action: 'editorial.published_update_ai_draft_prepared',
            subject: $request,
            after: [
                'ai_run_id' => $run->id,
                'resulting_english_editorial_content_id' => $run->english_editorial_content_id,
                'resulting_malayalam_editorial_content_id' => $run->malayalam_editorial_content_id,
            ],
            actor: $actor,
        );

        return $request->fresh() ?? $request;
    }

    /**
     * Save the editor-prepared EN/ML versions for the open maintenance
     * cycle. Pairing is established AT CREATION: a new ML successor is born
     * pointing at the cycle's proposed EN master, so the pair is correct
     * before either version is approved or customer-visible. Versions stay
     * drafts for editorial review; release gates on approved + paired.
     *
     * @param  array<string, mixed>|null  $enData
     * @param  array<string, mixed>|null  $mlData
     */
    public function prepareVersions(Application $application, User $actor, ?array $enData, ?array $mlData): EditorialRevisionRequest
    {
        if (! $actor->canManageEditorial()) {
            throw new InvalidArgumentException('Only editorial staff may prepare maintenance versions.');
        }

        $request = $this->openRequestFor($application);
        if ($request === null || ! $request->isAwaitingPreparation()) {
            throw new InvalidArgumentException('No maintenance request is awaiting editorial preparation.');
        }

        return DB::transaction(function () use ($application, $actor, $enData, $mlData, $request) {
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $profile = Profile::query()->whereKey($locked->profile_id)->lockForUpdate()->firstOrFail();

            $versioning = app(\App\Services\EditorialContentVersioningService::class);

            $baseEn = $request->resulting_english_editorial_content_id !== null
                ? EditorialContent::query()->find($request->resulting_english_editorial_content_id)
                : ($locked->published_english_editorial_content_id !== null ? EditorialContent::query()->find($locked->published_english_editorial_content_id) : null);
            $baseMl = $request->resulting_malayalam_editorial_content_id !== null
                ? EditorialContent::query()->find($request->resulting_malayalam_editorial_content_id)
                : ($locked->published_malayalam_editorial_content_id !== null ? EditorialContent::query()->find($locked->published_malayalam_editorial_content_id) : null);

            // EN first: its new identity anchors the ML pairing.
            $newEn = $enData !== null && $baseEn !== null
                ? $versioning->applyEditorUpdate($baseEn, $enData, $actor)
                : $baseEn;

            $newMl = null;
            if ($mlData !== null && $baseMl !== null && $newEn !== null) {
                if ($baseMl->status === EditorialContent::STATUS_DRAFT) {
                    // Working copy: edit in place and (re)establish pairing.
                    $newMl = $versioning->applyEditorUpdate($baseMl, $mlData, $actor);
                    if ((int) $newMl->source_editorial_content_id !== (int) $newEn->id) {
                        $newMl->forceFill(['source_editorial_content_id' => $newEn->id])->save();
                        $newMl = $newMl->fresh() ?? $newMl;
                    }
                } else {
                    // Approved prior proposed version: pairing is established
                    // on the CREATED successor — the original row is never
                    // modified.
                    $newMl = $versioning->applyEditorUpdate($baseMl, $mlData, $actor, $newEn->id);
                }
            } elseif ($mlData !== null && $baseMl === null && $newEn !== null) {
                throw new InvalidArgumentException('This profile has no Malayalam edition to prepare.');
            }

            $request->forceFill([
                'resulting_english_editorial_content_id' => $newEn?->id,
                'resulting_malayalam_editorial_content_id' => $newMl?->id,
                'status' => EditorialRevisionRequest::STATUS_IN_PROGRESS,
            ])->save();

            $this->auditLogger->log(
                action: 'editorial.published_update_versions_prepared',
                subject: $request,
                after: [
                    'resulting_english_editorial_content_id' => $newEn?->id,
                    'resulting_malayalam_editorial_content_id' => $newMl?->id,
                ],
                actor: $actor,
            );

            return $request->fresh() ?? $request;
        });
    }

    /**
     * Link the editor-prepared (approved) EN/ML successors to the request
     * and release them as the maintenance preview for customer approval.
     * The application stays published; only the customer-facing preview
     * pointers move. Release refuses unapproved or unpaired versions, so
     * the customer only ever sees correctly paired, approved content.
     */
    public function releaseMaintenancePreview(Application $application, User $actor): EditorialRevisionRequest
    {
        if (! $actor->canManageEditorial()) {
            throw new InvalidArgumentException('Only editorial staff may release a maintenance preview.');
        }

        $request = $this->openRequestFor($application);
        if ($request === null || ! in_array($request->status, [EditorialRevisionRequest::STATUS_SUBMITTED, EditorialRevisionRequest::STATUS_IN_PROGRESS], true)) {
            throw new InvalidArgumentException('No maintenance request is awaiting editorial preparation.');
        }

        return DB::transaction(function () use ($application, $actor, $request) {
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $profile = Profile::query()->whereKey($locked->profile_id)->lockForUpdate()->firstOrFail();

            $english = $request->resulting_english_editorial_content_id !== null
                ? EditorialContent::query()->find($request->resulting_english_editorial_content_id)
                : null;

            if ($english === null || $english->status !== EditorialContent::STATUS_APPROVED) {
                throw new InvalidArgumentException('Prepare and approve a revised English version before releasing the maintenance preview.');
            }

            $malayalam = $request->resulting_malayalam_editorial_content_id !== null
                ? EditorialContent::query()->find($request->resulting_malayalam_editorial_content_id)
                : null;

            if ($malayalam !== null) {
                if ($malayalam->status !== EditorialContent::STATUS_APPROVED) {
                    throw new InvalidArgumentException('The prepared Malayalam version is still a draft. Approve it before releasing.');
                }

                if ((int) $malayalam->source_editorial_content_id !== (int) $english->id) {
                    throw new InvalidArgumentException('The prepared Malayalam version is not paired with the prepared English master. Re-prepare the pair before releasing.');
                }
            } elseif ($locked->published_malayalam_editorial_content_id !== null) {
                throw new InvalidArgumentException('This profile has a published Malayalam edition. Prepare and approve a paired Malayalam version before releasing.');
            }

            $locked->forceFill([
                'preview_english_editorial_content_id' => $english->id,
                'preview_malayalam_editorial_content_id' => $malayalam?->id,
                'customer_preview_released_at' => now(),
            ])->save();

            $request->forceFill([
                'resulting_english_editorial_content_id' => $english->id,
                'resulting_malayalam_editorial_content_id' => $malayalam?->id,
                'status' => EditorialRevisionRequest::STATUS_CUSTOMER_PREVIEW,
                'maintenance_preview_released_at' => now(),
            ])->save();

            $this->auditLogger->log(
                action: 'editorial.published_update_preview_released',
                subject: $request,
                after: [
                    'preview_english_editorial_content_id' => $english->id,
                    'preview_malayalam_editorial_content_id' => $malayalam?->id,
                ],
                actor: $actor,
            );

            return $request->fresh() ?? $request;
        });
    }

    /**
     * Customer approval of the released maintenance preview. The approved
     * EN/ML version ids are preserved on the request and the application's
     * existing approval columns; publication remains a staff action.
     */
    public function approveMaintenancePreview(Application $application, User $member, int $englishContentId, ?string $ipAddress = null, ?string $userAgent = null): EditorialRevisionRequest
    {
        $this->assertApplicationOwner($application, $member);

        $lock = Cache::lock('published-profile-update-approval:application:'.$application->id, 15);
        if (! $lock->get()) {
            throw new InvalidArgumentException('Approval is already being processed. Please wait.');
        }

        try {
            return DB::transaction(function () use ($application, $member, $englishContentId, $ipAddress, $userAgent) {
                /** @var Application $locked */
                $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

                $request = $this->openRequestFor($locked);
                if ($request === null || $request->status !== EditorialRevisionRequest::STATUS_CUSTOMER_PREVIEW) {
                    throw new InvalidArgumentException('Your updated profile is not currently awaiting your approval.');
                }

                if ((int) $locked->preview_english_editorial_content_id !== $englishContentId) {
                    throw new InvalidArgumentException('The proposed profile has been updated. Please review the latest version.');
                }

                $english = EditorialContent::query()->find($englishContentId);
                if ($english === null || (int) $english->profile_id !== (int) $locked->profile_id) {
                    throw new InvalidArgumentException('Invalid proposed version.');
                }

                $malayalam = $locked->preview_malayalam_editorial_content_id !== null
                    ? EditorialContent::query()->find($locked->preview_malayalam_editorial_content_id)
                    : null;

                // A5 — one active approval per application: invalidate any
                // earlier active approval record before recording this one,
                // exactly as the pre-publication approval flow does.
                \App\Models\EditorialCustomerApproval::query()
                    ->where('application_id', $locked->id)
                    ->whereNull('invalidated_at')
                    ->update([
                        'invalidated_at' => now(),
                        'invalidation_reason' => 'Superseded by a newer maintenance-update approval.',
                        'updated_at' => now(),
                    ]);

                \App\Models\EditorialCustomerApproval::query()->create([
                    'application_id' => $locked->id,
                    'profile_id' => $locked->profile_id,
                    'approved_by_user_id' => $member->id,
                    'english_editorial_content_id' => $english->id,
                    'malayalam_editorial_content_id' => $malayalam?->id,
                    'approved_at' => now(),
                ]);

                $locked->forceFill([
                    'customer_approved_at' => now(),
                    'customer_approved_english_editorial_content_id' => $english->id,
                ])->save();

                $request->forceFill([
                    'approved_english_editorial_content_id' => $english->id,
                    'approved_malayalam_editorial_content_id' => $malayalam?->id,
                    'status' => EditorialRevisionRequest::STATUS_CUSTOMER_APPROVED,
                ])->save();

                $this->auditLogger->log(
                    action: 'editorial.published_update_customer_approved',
                    subject: $request,
                    after: [
                        'approved_english_editorial_content_id' => $english->id,
                        'approved_malayalam_editorial_content_id' => $malayalam?->id,
                    ],
                    actor: $member,
                );

                return $request->fresh() ?? $request;
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Customer minor-correction request on the released maintenance preview.
     * Part of the same maintenance cycle: no new entitlement, no new charge,
     * no new request row.
     */
    public function requestCorrection(Application $application, User $member, string $correctionText): EditorialRevisionRequest
    {
        $this->assertApplicationOwner($application, $member);

        $text = $this->sanitizer->sanitizeBody($correctionText);
        if (mb_strlen($text) < 5) {
            throw new InvalidArgumentException('Please describe the corrections you need.');
        }
        if (mb_strlen($text) > 5000) {
            throw new InvalidArgumentException('Correction text is too long.');
        }

        return DB::transaction(function () use ($application, $member, $text) {
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            $request = $this->openRequestFor($locked);
            if ($request === null || $request->status !== EditorialRevisionRequest::STATUS_CUSTOMER_PREVIEW) {
                throw new InvalidArgumentException('There is no proposed profile currently awaiting your review.');
            }

            $request->forceFill([
                'customer_correction_text' => $text,
                'status' => EditorialRevisionRequest::STATUS_IN_PROGRESS,
            ])->save();

            $this->auditLogger->log(
                action: 'editorial.published_update_correction_requested',
                subject: $request,
                after: ['correction_length' => mb_strlen($text)],
                actor: $member,
            );

            return $request->fresh() ?? $request;
        });
    }

    /**
     * Final Jannayaks publication of the customer-approved maintenance
     * update: reuses the existing replacement-publication machinery, then
     * completes the maintenance request with the published version pointers.
     */
    public function completePublication(Application $application, User $actor): EditorialRevisionRequest
    {
        if (! $actor->isAdmin() || ! $actor->isActiveAccount()) {
            throw new InvalidArgumentException('Only an authorised Admin may publish a maintenance update.');
        }

        $request = $this->openRequestFor($application);
        if ($request === null || $request->status !== EditorialRevisionRequest::STATUS_CUSTOMER_APPROVED) {
            throw new InvalidArgumentException('Customer approval is required before publishing a maintenance update.');
        }

        return DB::transaction(function () use ($application, $actor, $request) {
            // No EditorialContent mutation happens here: the EN/ML pairing
            // was established when the proposed versions were created and
            // validated at release. Publication consumes the already-correct
            // approved versions via the existing replacement-publication
            // machinery.
            app(\App\Services\ApplicationWorkflowService::class)->publish($application, $actor, false);
            $application->refresh();

            $request->forceFill([
                'approved_english_editorial_content_id' => $application->published_english_editorial_content_id,
                'approved_malayalam_editorial_content_id' => $application->published_malayalam_editorial_content_id,
                'status' => EditorialRevisionRequest::STATUS_COMPLETED,
                'processed_by_user_id' => $actor->id,
                'processed_at' => now(),
            ])->save();

            $this->auditLogger->log(
                action: 'editorial.published_update_completed',
                subject: $request,
                after: [
                    'published_english_editorial_content_id' => $application->published_english_editorial_content_id,
                    'published_malayalam_editorial_content_id' => $application->published_malayalam_editorial_content_id,
                ],
                actor: $actor,
            );

            return $request->fresh() ?? $request;
        });
    }
}
