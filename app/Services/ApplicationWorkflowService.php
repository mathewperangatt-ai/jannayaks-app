<?php

namespace App\Services;

use App\Models\Application;
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
    public function markInterviewSubmitted(Application $application): Application
    {
        return DB::transaction(function () use ($application) {
            $application->online_interview_completed_at = now();

            $unlocked = app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($application);

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
                if (! in_array($application->status, $terminalOrLater, true)) {
                    $application->status = Application::STATUS_AWAITING_EDITORIAL_REVIEW;
                }
            } else {
                $application->status = Application::STATUS_INTERVIEW_SUBMITTED;
            }

            $application->save();

            return $application->fresh() ?? $application;
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
            $this->assertEditorialStatusChangeAllowed($application, $newStatus, $actor);
            $allowed['status'] = $newStatus;
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

            if ($locked->status === Application::STATUS_PUBLISHED) {
                throw new InvalidArgumentException('This application is already published.');
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

            if (! $locked->profile_id) {
                throw new InvalidArgumentException('A linked profile is required before publication.');
            }

            $before = ['status' => $locked->status];
            $locked->forceFill([
                'status' => Application::STATUS_PUBLISHED,
                'converted_to_profile_at' => $locked->converted_to_profile_at ?? now(),
            ])->save();

            Profile::query()->whereKey($locked->profile_id)->update([
                'status' => 'published',
                'published_at' => now(),
                'updated_at' => now(),
            ]);

            $profile = Profile::query()->findOrFail($locked->profile_id);
            app(ProfileUrlService::class)->assignInitialCanonicalSlug(
                $profile,
                (string) $locked->package_tier,
            );

            $this->auditLogger->log(
                action: 'application.published',
                subject: $locked,
                before: $before,
                after: [
                    'status' => Application::STATUS_PUBLISHED,
                    'require_customer_approval' => $requireCustomerApproval,
                    'customer_approved_english_editorial_content_id' => $locked->customer_approved_english_editorial_content_id,
                ],
                actor: $actor,
            );

            return $locked->fresh() ?? $locked;
        });
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
