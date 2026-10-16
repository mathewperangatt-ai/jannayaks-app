<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApplicationPaymentStateService
{
    /**
     * TEMPORARY pre-gateway-launch testing mode. While active the payment
     * requirement is suspended for every application (no fake settlements are
     * recorded; applications simply stay in their pre-payment state) and
     * payment initiation endpoints are refused. Controlled solely by
     * jannayaks.payments.testing_mode (JANNAYAKS_PAYMENTS_TESTING_MODE).
     */
    public static function testingModeActive(): bool
    {
        return (bool) config('jannayaks.payments.testing_mode', false);
    }

    public function afterSettled(Payment $payment): Application
    {
        $application = $payment->application;
        if (! $application instanceof Application) {
            throw new \RuntimeException('Payment is not associated with an application.');
        }

        return DB::transaction(function () use ($application, $payment) {
            $cols = [];
            $cols['payment_status'] = 'paid';
            if (! isset($application->payment_settled_at)) {
                $cols['payment_settled_at'] = $payment->paid_at ?? now();
            }

            $nextWorkflow = $this->nextWorkflowStateAfterPayment($application);
            if ($nextWorkflow !== null) {
                $cols['status'] = $nextWorkflow;
            }

            if ($cols !== []) {
                $application->forceFill($cols)->save();
            }

            return $application->fresh() ?? $application;
        });
    }

    private function nextWorkflowStateAfterPayment(Application $application): ?string
    {
        $current = $application->status ?? null;

        if ($current === null || $current === '' || $current === 'intake_in_progress') {
            if ($application->source_method === 'direct_submission') {
                return 'awaiting_editorial_review';
            }
            if ($application->isInterviewSubmitted()) {
                return 'awaiting_editorial_review';
            }

            return 'payment_complete_awaiting_interview';
        }

        $paidTransitions = [
            'interview_in_progress'     => 'payment_complete_awaiting_interview',
            'interview_submitted'       => 'awaiting_editorial_review',
            'direct_submitted'          => 'awaiting_editorial_review',
            'payment_pending'           => 'payment_complete_awaiting_interview',
        ];

        return $paidTransitions[$current] ?? null;
    }

    public function isPaymentSettled(Application $application): bool
    {
        return $this->hasSettledPaymentRecord($application)
            || $this->hasTrustedStaffWaiver($application);
    }

    /**
     * Record an audited staff waiver. Does not create a payment, a discount,
     * or a gateway event. Publication and interview unlock both honour the
     * same trusted-waiver check.
     */
    public function grantStaffWaiver(Application $application, User $actor, string $reason): Application
    {
        if (! $actor->canManageFinance()) {
            throw new \InvalidArgumentException('Only an active admin may waive payment.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 8) {
            throw new \InvalidArgumentException('A waiver reason is required.');
        }

        if ($application->package_tier === 'in_memoriam') {
            throw new \InvalidArgumentException('In Memoriam payment is arranged outside this waiver.');
        }

        if ($this->hasSettledPaymentRecord($application)) {
            throw new \InvalidArgumentException('This application already has a settled payment.');
        }

        return DB::transaction(function () use ($application, $actor, $reason) {
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if ($this->hasSettledPaymentRecord($locked)) {
                throw new \InvalidArgumentException('This application already has a settled payment.');
            }

            $before = [
                'payment_status' => $locked->payment_status,
                'status' => $locked->status,
                'waived_by_user_id' => $locked->waived_by_user_id,
            ];

            $cols = [
                'payment_status' => Application::PAYMENT_STATUS_WAIVED,
                'waived_by_user_id' => $actor->id,
                'payment_settled_at' => $locked->payment_settled_at ?? now(),
            ];

            $next = $this->nextWorkflowStateAfterPayment($locked);
            if ($next !== null) {
                $cols['status'] = $next;
            }

            $locked->forceFill($cols)->save();

            app(StaffAuditLogger::class)->log(
                action: 'application.payment_waived',
                subject: $locked,
                before: $before,
                after: [
                    'payment_status' => $locked->payment_status,
                    'status' => $locked->status,
                    'waived_by_user_id' => $actor->id,
                    'reason' => mb_substr($reason, 0, 500),
                ],
                actor: $actor,
            );

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * Interview and source-material uploads unlock only after a settled Payment row
     * or an explicit trusted staff waiver / admin test demo.
     */
    public function unlocksInterviewOrUploads(Application $application): bool
    {
        // TEMPORARY testing mode: the payment requirement is suspended, so
        // testers reach the interview and uploads without any payment record.
        // Genuine settlements, staff waivers and admin_test_demo keep their
        // existing meaning (they remain the unlock path once testing ends).
        if (self::testingModeActive()) {
            return true;
        }

        if ($application->source_method === 'admin_test_demo') {
            return true;
        }

        if ($this->hasTrustedStaffWaiver($application)) {
            return true;
        }

        return $this->hasSettledPaymentRecord($application);
    }

    private function hasSettledPaymentRecord(Application $application): bool
    {
        return Payment::query()
            ->where('application_id', $application->id)
            ->whereIn('status', [
                Payment::STATUS_PAID,
                Payment::STATUS_CAPTURED,
                Payment::STATUS_SUCCESS,
            ])
            ->exists();
    }

    private function hasTrustedStaffWaiver(Application $application): bool
    {
        if ($application->waived_by_user_id === null) {
            return false;
        }

        if (strtolower((string) ($application->payment_status ?? '')) !== 'waived') {
            return false;
        }

        $waver = User::query()->find($application->waived_by_user_id);
        if (! $waver instanceof User) {
            return false;
        }

        return $waver->isStaff() && ($waver->account_status ?? 'active') === 'active';
    }
}
