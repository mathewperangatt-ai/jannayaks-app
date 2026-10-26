<?php

namespace App\Services;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialCustomerApproval;
use App\Models\Profile;
use App\Models\User;
use App\Support\PublicationTestBypass;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApplicationWorkflowService
{
    public function __construct(private StaffAuditLogger $auditLogger) {}

    /**
     * After Online Interview submit: mark completed and advance workflow status.
     */
    public function markInterviewSubmitted(
        Application $application,
        ?User $actor = null,
        ?string $questionnaireVersion = null,
        ?string $submissionHash = null,
    ): Application {
        return DB::transaction(function () use ($application, $actor, $questionnaireVersion, $submissionHash) {
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $beforeStatus = $locked->status;
            $locked->online_interview_completed_at = now();
            if ($questionnaireVersion !== null && $submissionHash !== null) {
                $locked->questionnaire_confirmed_at = now();
                $locked->questionnaire_version = $questionnaireVersion;
                $locked->questionnaire_submission_sha256 = $submissionHash;
            }

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
                        'questionnaire_confirmed_at' => $locked->questionnaire_confirmed_at?->toIso8601String(),
                        'questionnaire_version' => $locked->questionnaire_version,
                        'questionnaire_submission_sha256' => $locked->questionnaire_submission_sha256,
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
     * Publication-test-only: mark the exact unchanged English draft the customer
     * already approved as editorially approved. Does not edit narrative, clear
     * the customer approval, approve Malayalam, or write a payment.
     */
    public function mayApprovePublicationTestEnglishDraft(?Application $application): bool
    {
        if (! $application instanceof Application || $application->id === null) {
            return false;
        }

        if (! app(PublicationTestBypass::class)->appliesTo($application)) {
            return false;
        }

        if ($application->status !== Application::STATUS_AWAITING_PUBLICATION
            || $application->customer_approved_at === null
            || $application->customer_approved_english_editorial_content_id === null) {
            return false;
        }

        $approval = EditorialCustomerApproval::query()
            ->where('application_id', $application->id)
            ->whereNull('invalidated_at')
            ->first();

        if (! $approval instanceof EditorialCustomerApproval
            || (int) $approval->english_editorial_content_id !== (int) $application->customer_approved_english_editorial_content_id
            || $approval->approved_at === null
            || ! $approval->approved_at->equalTo($application->customer_approved_at)) {
            return false;
        }

        $english = EditorialContent::query()->find($approval->english_editorial_content_id);

        return $english instanceof EditorialContent
            && (int) $english->profile_id === (int) $application->profile_id
            && $english->language === EditorialContent::LANGUAGE_EN
            && $english->status === EditorialContent::STATUS_DRAFT
            && $english->updated_at !== null
            && ! $english->updated_at->greaterThan($approval->approved_at);
    }

    /**
     * Admin-only status transition for the publication test. The customer
     * approval record and the application pin must name the same English draft,
     * and that draft's text must be unchanged since the customer approved it.
     */
    public function approvePublicationTestEnglishDraft(Application $application, User $actor): EditorialContent
    {
        if (! $actor->isAdmin() || ! $actor->isActiveAccount()) {
            throw new InvalidArgumentException('Only an authorised Admin may approve an editorial draft for the publication test.');
        }

        return DB::transaction(function () use ($application, $actor) {
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if (! $locked->profile_id) {
                throw new InvalidArgumentException('The publication test bypass is not active for this profile.');
            }

            /** @var Profile $profile */
            $profile = Profile::query()->whereKey($locked->profile_id)->lockForUpdate()->firstOrFail();
            $locked->setRelation('profile', $profile);

            if (! app(PublicationTestBypass::class)->appliesTo($locked)) {
                throw new InvalidArgumentException('The publication test bypass is not active for this profile.');
            }

            if ($locked->status !== Application::STATUS_AWAITING_PUBLICATION) {
                throw new InvalidArgumentException('The application is not awaiting publication of a customer-approved version.');
            }

            if ($locked->customer_approved_at === null || $locked->customer_approved_english_editorial_content_id === null) {
                throw new InvalidArgumentException('An active customer approval of the exact English version is required.');
            }

            $approval = EditorialCustomerApproval::query()
                ->where('application_id', $locked->id)
                ->whereNull('invalidated_at')
                ->lockForUpdate()
                ->first();

            if (! $approval instanceof EditorialCustomerApproval) {
                throw new InvalidArgumentException('An active customer approval of the exact English version is required.');
            }

            if ((int) $approval->english_editorial_content_id !== (int) $locked->customer_approved_english_editorial_content_id
                || $approval->approved_at === null
                || ! $approval->approved_at->equalTo($locked->customer_approved_at)) {
                throw new InvalidArgumentException('The customer approval record does not match the application\'s approved English version.');
            }

            $english = EditorialContent::query()
                ->whereKey($approval->english_editorial_content_id)
                ->lockForUpdate()
                ->first();

            if (! $english instanceof EditorialContent
                || (int) $english->profile_id !== (int) $profile->id
                || $english->language !== EditorialContent::LANGUAGE_EN
                || (int) $english->id !== (int) $locked->customer_approved_english_editorial_content_id) {
                throw new InvalidArgumentException('The customer approval record does not match the application\'s approved English version.');
            }

            if ($english->status !== EditorialContent::STATUS_DRAFT) {
                throw new InvalidArgumentException('The customer-approved English version is not an unapproved draft.');
            }

            if ($english->updated_at === null || $english->updated_at->greaterThan($approval->approved_at)) {
                throw new InvalidArgumentException('The customer-approved English version has changed since the customer approved it.');
            }

            $narrative = [
                'title' => (string) $english->title,
                'summary' => (string) $english->summary,
                'body' => (string) $english->body,
                'language' => (string) $english->language,
                'version_number' => (int) $english->version_number,
            ];

            $beforeStatus = $english->status;
            $english->forceFill([
                'status' => EditorialContent::STATUS_APPROVED,
                'reviewed_by_id' => $actor->id,
            ])->save();
            $english->refresh();

            $unchanged = (string) $english->title === $narrative['title']
                && (string) $english->summary === $narrative['summary']
                && (string) $english->body === $narrative['body']
                && (string) $english->language === $narrative['language']
                && (int) $english->version_number === $narrative['version_number'];

            if (! $unchanged) {
                throw new InvalidArgumentException('The customer-approved English version has changed since the customer approved it.');
            }

            $locked->refresh();
            if ((int) $locked->customer_approved_english_editorial_content_id !== (int) $english->id
                || $locked->customer_approved_at === null) {
                throw new InvalidArgumentException('The customer approval record does not match the application\'s approved English version.');
            }

            $this->auditLogger->log(
                action: 'editorial.publication_test_english_draft_approved',
                subject: $english,
                before: [
                    'status' => $beforeStatus,
                    'editorial_content_id' => $english->id,
                    'version_number' => $english->version_number,
                ],
                after: [
                    'status' => $english->status,
                    'editorial_content_id' => $english->id,
                    'version_number' => $english->version_number,
                    'language' => $english->language,
                    'application_id' => $locked->id,
                    'customer_approval_id' => $approval->id,
                    'customer_approved_english_editorial_content_id' => $locked->customer_approved_english_editorial_content_id,
                    'reason' => 'Publication test only: mark the unchanged customer-approved English draft as editorially approved.',
                ],
                actor: $actor,
            );

            return $english;
        });
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

            $locked->setRelation('profile', $profile);
            $publicationBypass = app(PublicationTestBypass::class)->appliesTo($locked);

            if (! $locked->isPaymentSettled() && $locked->source_method !== 'admin_test_demo' && ! $publicationBypass) {
                throw new InvalidArgumentException('Payment must be settled before publication.');
            }

            if ($publicationBypass && ! $requireCustomerApproval) {
                throw new InvalidArgumentException('The publication test bypass requires customer approval of the exact approved English version.');
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
            $this->assertResolvedVersionMayBePublished($locked, $english, $requireCustomerApproval);
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
                $actor,
            );

            // Living-profile annual membership begins at publication (not In Memoriam).
            app(MembershipLifecycleService::class)->startMembershipForPublishedProfile($profile);

            // Known-good integrity baseline for the newly published public
            // state (same transaction — a failed snapshot write rolls back
            // publication rather than leaving a stale baseline).
            app(ProfileIntegrityService::class)->refreshSnapshot(
                $profile,
                $isReplacement ? 'application.published.replacement' : 'application.published',
            );

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
                    'publication_test_bypass' => $publicationBypass,
                ],
                actor: $actor,
            );

            if ($publicationBypass) {
                $this->auditLogger->log(
                    action: 'application.publication_test_bypass_used',
                    subject: $locked,
                    after: [
                        'profile_reference_code' => $profile->reference_code,
                        'payment_status' => $locked->payment_status,
                        'published_english_editorial_content_id' => $locked->published_english_editorial_content_id,
                        'customer_approved_english_editorial_content_id' => $locked->customer_approved_english_editorial_content_id,
                    ],
                    actor: $actor,
                );
            }

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * Hide a profile that was published through the publication test bypass.
     * Does not record a payment change and does not require the flag to
     * still be enabled.
     */
    public function unpublishPublicationTest(Application $application, User $actor): Application
    {
        if (! $actor->isAdmin() || ! $actor->isActiveAccount()) {
            throw new InvalidArgumentException('Only an authorised Admin may unpublish.');
        }

        return DB::transaction(function () use ($application, $actor) {
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            if (! $locked->profile_id) {
                throw new InvalidArgumentException('A linked profile is required.');
            }

            /** @var Profile $profile */
            $profile = Profile::query()->whereKey($locked->profile_id)->lockForUpdate()->firstOrFail();
            $locked->setRelation('profile', $profile);

            if (! app(PublicationTestBypass::class)->mayUnpublish($locked)) {
                throw new InvalidArgumentException('Only a profile published through the publication test bypass can be unpublished this way.');
            }

            $before = [
                'application_status' => $locked->status,
                'profile_status' => $profile->status,
                'unpublished_at' => $profile->unpublished_at?->toIso8601String(),
                'payment_status' => $locked->payment_status,
            ];

            $locked->forceFill([
                'status' => Application::STATUS_AWAITING_PUBLICATION,
            ])->save();

            $profile->forceFill([
                'status' => 'customer_preview',
                'unpublished_at' => now(),
                'updated_at' => now(),
            ])->save();

            $this->auditLogger->log(
                action: 'application.publication_test_unpublished',
                subject: $locked,
                before: $before,
                after: [
                    'application_status' => $locked->status,
                    'profile_status' => $profile->status,
                    'unpublished_at' => $profile->unpublished_at?->toIso8601String(),
                    'payment_status' => $locked->payment_status,
                    'profile_reference_code' => $profile->reference_code,
                ],
                actor: $actor,
            );

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * Publish only the English version resolveEnglishVersionToPublish selected.
     * An obsolete preview or customer-approval pin may still point at an
     * archived predecessor during replacement publication; that pin is not
     * the version going live. A draft, or any selection that is not the
     * approved customer-approved version on the normal path, cannot be published.
     */
    private function assertResolvedVersionMayBePublished(
        Application $application,
        ?EditorialContent $english,
        bool $requireCustomerApproval,
    ): void {
        if ($english instanceof EditorialContent && $english->status === EditorialContent::STATUS_APPROVED) {
            if ($requireCustomerApproval
                && (int) $english->id !== (int) $application->customer_approved_english_editorial_content_id) {
                throw new InvalidArgumentException('Customer approval of a specific editorial version is required.');
            }

            return;
        }

        $pinIds = array_values(array_unique(array_filter([
            $application->customer_approved_english_editorial_content_id,
            $application->preview_english_editorial_content_id,
        ])));
        $unapprovedPin = $pinIds !== [] && EditorialContent::query()
            ->where('profile_id', $application->profile_id)
            ->whereIn('id', $pinIds)
            ->where('status', '!=', EditorialContent::STATUS_APPROVED)
            ->exists();

        if ($unapprovedPin) {
            throw new InvalidArgumentException('An unapproved editorial draft cannot be published.');
        }

        if ($requireCustomerApproval) {
            throw new InvalidArgumentException('Customer approval of a specific editorial version is required.');
        }
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
