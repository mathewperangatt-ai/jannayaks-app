<?php

namespace App\Services;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApplicationWorkflowService
{
    public function __construct(private StaffAuditLogger $auditLogger) {}

    /**
     * After Online Interview submit: mark completed and advance workflow status.
     */
    public function markInterviewSubmitted(Application $application, ?User $actor = null): Application
    {
        return DB::transaction(function () use ($application, $actor) {
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $beforeStatus = $locked->status;
            $locked->online_interview_completed_at = now();

            $unlocked = app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($locked);

            $terminalOrLater = [
                Application::STATUS_IN_EDITORIAL_REVIEW,
                Application::STATUS_EDITORIAL_APPROVED,
                Application::STATUS_EDITORIAL_REVISION_REQUESTED,
                Application::STATUS_AWAITING_PUBLICATION,
                Application::STATUS_PUBLISHED,
                Application::STATUS_ARCHIVED,
                Application::STATUS_CANCELLED,
                Application::STATUS_REFUNDED,
            ];

            if ($unlocked) {
                if (! in_array($locked->status, $terminalOrLater, true)) {
                    $locked->status = Application::STATUS_AWAITING_EDITORIAL_REVIEW;
                }
            } else {
                $locked->status = Application::STATUS_INTERVIEW_SUBMITTED;
            }

            $locked->save();

            if ($beforeStatus !== $locked->status) {
                $this->auditLogger->log(
                    action: 'application.questionnaire_submitted',
                    subject: $locked,
                    before: ['status' => $beforeStatus],
                    after: [
                        'status' => $locked->status,
                        'online_interview_completed_at' => $locked->online_interview_completed_at?->toIso8601String(),
                    ],
                    actor: $actor,
                );
            }

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateStaffFields(Application $application, array $attributes, User $actor): Application
    {
        $allowed = [];

        if ($actor->canManageEditorial() && array_key_exists('status', $attributes)) {
            $newStatus = (string) $attributes['status'];
            if ($newStatus === Application::STATUS_PUBLISHED) {
                if ($application->status !== Application::STATUS_PUBLISHED) {
                    throw new InvalidArgumentException('Publication must use the authorised Publish action.');
                }
            } else {
                $this->assertEditorialStatusChangeAllowed($application, $newStatus, $actor);
                $allowed['status'] = $newStatus;
            }
        }

        if ($allowed === []) {
            return $application;
        }

        $before = [];
        foreach (array_keys($allowed) as $key) {
            $before[$key] = $application->getAttribute($key);
        }

        $application->forceFill($allowed)->save();

        $this->auditLogger->log(
            action: 'application.staff_update',
            subject: $application,
            before: $before,
            after: $allowed,
            actor: $actor,
        );

        return $application->fresh() ?? $application;
    }

    /**
     * Admin publication for normal (customer-approved) or exceptional offline/admin workflows.
     * Editors and members cannot publish.
     */
    public function publish(Application $application, User $actor, bool $requireCustomerApproval = true): Application
    {
        if (! $actor->isAdmin() || ! $actor->isActiveAccount()) {
            throw new InvalidArgumentException('Only an authorised Admin may publish.');
        }

        if ($application->package_tier === 'in_memoriam') {
            throw new InvalidArgumentException('In Memoriam publication is outside the living-profile workflow.');
        }

        return DB::transaction(function () use ($application, $actor, $requireCustomerApproval) {
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if (! $locked->profile_id) {
                throw new InvalidArgumentException('A linked profile is required before publication.');
            }

            /** @var Profile $profile */
            $profile = Profile::query()->whereKey($locked->profile_id)->lockForUpdate()->firstOrFail();

            $isReplacement = $locked->status === Application::STATUS_PUBLISHED
                || ($profile->status === 'published' && $profile->published_at !== null && $profile->unpublished_at === null);

            if ($locked->status === Application::STATUS_PUBLISHED && $requireCustomerApproval) {
                throw new InvalidArgumentException('Replacement publication requires a fresh customer-approved version awaiting publication.');
            }

            if (! $locked->isPaymentSettled() && $locked->source_method !== 'admin_test_demo') {
                throw new InvalidArgumentException('Payment must be settled before publication.');
            }

            if ($requireCustomerApproval) {
                if ($locked->status !== Application::STATUS_AWAITING_PUBLICATION) {
                    throw new InvalidArgumentException('Normal publication requires customer approval (awaiting publication).');
                }
                if ($locked->customer_approved_at === null || $locked->customer_approved_english_editorial_content_id === null) {
                    throw new InvalidArgumentException('Customer approval of a specific editorial version is required.');
                }
            }

            $english = $this->resolveEnglishVersionToPublish($locked, $requireCustomerApproval);
            $malayalam = $english instanceof EditorialContent
                ? $this->resolveMalayalamVersionToPublish($profile, $english)
                : null;

            if ($requireCustomerApproval && ! $english instanceof EditorialContent) {
                throw new InvalidArgumentException('Customer approval of a specific editorial version is required.');
            }

            $before = [
                'status' => $locked->status,
                'published_english_editorial_content_id' => $locked->published_english_editorial_content_id,
            ];
            $locked->forceFill([
                'status' => Application::STATUS_PUBLISHED,
                'converted_to_profile_at' => $locked->converted_to_profile_at ?? now(),
                'published_english_editorial_content_id' => $english?->id,
                'published_malayalam_editorial_content_id' => $malayalam?->id,
            ])->save();

            $profile->forceFill([
                'status' => 'published',
                'published_at' => $profile->published_at ?? now(),
                'unpublished_at' => null,
                'updated_at' => now(),
            ])->save();

            app(ProfileUrlService::class)->assignInitialCanonicalSlug(
                $profile,
                (string) $locked->package_tier,
            );

            // Living-profile annual membership begins at publication (not In Memoriam).
            app(MembershipLifecycleService::class)->startMembershipForPublishedProfile($profile);

            $this->auditLogger->log(
                action: $isReplacement ? 'application.published.replacement' : 'application.published',
                subject: $locked,
                before: $before,
                after: [
                    'status' => Application::STATUS_PUBLISHED,
                    'require_customer_approval' => $requireCustomerApproval,
                    'published_english_editorial_content_id' => $locked->published_english_editorial_content_id,
                    'published_malayalam_editorial_content_id' => $locked->published_malayalam_editorial_content_id,
                    'customer_approved_english_editorial_content_id' => $locked->customer_approved_english_editorial_content_id,
                ],
                actor: $actor,
            );

            return $locked->fresh() ?? $locked;
        });
    }

    private function resolveEnglishVersionToPublish(Application $application, bool $requireCustomerApproval): ?EditorialContent
    {
        $candidateIds = [];
        if ($application->customer_approved_english_editorial_content_id) {
            $candidateIds[] = (int) $application->customer_approved_english_editorial_content_id;
        }
        if (! $requireCustomerApproval && $application->preview_english_editorial_content_id) {
            $candidateIds[] = (int) $application->preview_english_editorial_content_id;
        }

        foreach (array_unique($candidateIds) as $contentId) {
            $english = EditorialContent::query()
                ->whereKey($contentId)
                ->where('profile_id', $application->profile_id)
                ->where('language', EditorialContent::LANGUAGE_EN)
                ->where('status', EditorialContent::STATUS_APPROVED)
                ->first();
            if ($english instanceof EditorialContent) {
                return $english;
            }
        }

        if ($requireCustomerApproval) {
            return null;
        }

        // Exceptional admin publication freezes the approved version present at publish time.
        return EditorialContent::query()
            ->where('profile_id', $application->profile_id)
            ->where('language', EditorialContent::LANGUAGE_EN)
            ->where('status', EditorialContent::STATUS_APPROVED)
            ->orderByDesc('version_number')
            ->first();
    }

    private function resolveMalayalamVersionToPublish(Profile $profile, EditorialContent $english): ?EditorialContent
    {
        return EditorialContent::query()
            ->where('profile_id', $profile->id)
            ->where('language', EditorialContent::LANGUAGE_ML)
            ->where('status', EditorialContent::STATUS_APPROVED)
            ->where('source_editorial_content_id', $english->id)
            ->orderByDesc('version_number')
            ->first();
    }

    private function assertEditorialStatusChangeAllowed(Application $application, string $newStatus, User $actor): void
    {
        // Admins have unrestricted internal workflow authority, including publication-terminal
        // states for completed/offline biographies. Customer preview/approval remains the normal P11 path.
        if ($actor->isAdmin()) {
            if (! array_key_exists($newStatus, Application::workflowStatusLabels())) {
                throw new InvalidArgumentException('Invalid application status.');
            }

            return;
        }

        $handoff = Application::editorialHandoffStatuses();
        if (! in_array($newStatus, $handoff, true)) {
            throw new InvalidArgumentException('Editors may only set editorial handoff statuses.');
        }

        $current = (string) $application->status;
        if (in_array($current, $handoff, true)) {
            return;
        }

        if ($application->isReadyForEditorialQueue()
            || in_array($current, [
                Application::STATUS_INTERVIEW_SUBMITTED,
                Application::STATUS_DIRECT_SUBMITTED,
                Application::STATUS_PAYMENT_COMPLETE_AWAITING_INTERVIEW,
            ], true)) {
            return;
        }

        throw new InvalidArgumentException('Application is not in an editorial-editable state.');
    }
}
