<?php

namespace App\Services;

use App\Models\Application;
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

    private function assertEditorialStatusChangeAllowed(Application $application, string $newStatus, User $actor): void
    {
        // Admins have unrestricted internal workflow authority, including publication-terminal
        // states for completed/offline biographies. This is not the P11 customer preview flow.
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
