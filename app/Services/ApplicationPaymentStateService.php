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
        return $this->hasCollectedPayment($application) || $this->hasTrustedStaffWaiver($application);
    }

    /**
     * A collected gateway or manual payment. A staff waiver is not a collected payment.
     */
    public function hasCollectedPayment(Application $application): bool
    {
        return $this->hasSettledPaymentRecord($application);
    }

    /**
     * Record an administrative waiver. A trusted waiver settles the
     * application for workflow and publication without creating a paid
     * gateway payment. It cannot replace a payment that was already collected.
     */
    public function recordStaffWaiver(Application $application, User $actor, string $reason): Application
    {
        if (! $actor->isAdmin() || ! $actor->isActiveAccount()) {
            throw new \InvalidArgumentException('Only an authorised Admin may apply a payment waiver.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A written reason is required to apply a payment waiver.');
        }

        return DB::transaction(function () use ($application, $actor, $reason) {
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->payment_status, [Application::PAYMENT_STATUS_REFUNDED, Application::PAYMENT_STATUS_PARTIALLY_REFUNDED], true)) {
                throw new \InvalidArgumentException('A refunded application cannot be waived.');
            }

            if ($this->hasSettledPaymentRecord($locked)) {
                throw new \InvalidArgumentException('A collected payment already exists. A waiver would make the payment state inconsistent.');
            }

            if ($this->hasTrustedStaffWaiver($locked)) {
                throw new \InvalidArgumentException('A payment waiver is already recorded for this application.');
            }

            Payment::query()->create([
                'application_id' => $locked->id,
                'profile_id' => $locked->profile_id,
                'transaction_reference' => 'WAIVER-'.$locked->id.'-'.strtoupper(bin2hex(random_bytes(4))),
                'gateway' => Payment::GATEWAY_WAIVER,
                'item_type' => Payment::ITEM_APPLICATION_PAYMENT,
                'amount' => '0.00',
                'currency' => 'INR',
                'status' => Payment::STATUS_WAIVED,
                'event_type' => Payment::EVENT_WAIVER,
                'waiver_reason' => mb_substr($reason, 0, 255),
                'waived_by_user_id' => $actor->id,
                'waived_at' => now(),
            ]);

            $cols = [
                'payment_status' => Application::PAYMENT_STATUS_WAIVED,
                'waived_by_user_id' => $actor->id,
                'payment_settled_at' => $locked->payment_settled_at ?? now(),
            ];
            $nextWorkflow = $this->nextWorkflowStateAfterPayment($locked);
            if ($nextWorkflow !== null) {
                $cols['status'] = $nextWorkflow;
            }
            $locked->forceFill($cols)->save();

            app(StaffAuditLogger::class)->log(
                action: 'application.payment_waived',
                subject: $locked,
                before: [
                    'payment_status' => $application->payment_status,
                    'status' => $application->status,
                ],
                after: [
                    'payment_status' => Application::PAYMENT_STATUS_WAIVED,
                    'status' => $locked->status,
                    'waived_by_user_id' => $actor->id,
                    'waiver_reason' => mb_substr($reason, 0, 255),
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
