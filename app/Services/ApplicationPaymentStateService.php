<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Payment;
use App\Support\PricingAmounts;
use Illuminate\Support\Facades\DB;

class ApplicationPaymentStateService
{
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
                $application->update($cols);
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
        $status = $application->payment_status ?? null;
        if (is_string($status) && strtolower($status) === 'paid') {
            return true;
        }

        return Payment::query()
            ->where('application_id', $application->id)
            ->whereIn('status', [
                Payment::STATUS_PAID,
                Payment::STATUS_CAPTURED,
                Payment::STATUS_SUCCESS,
            ])
            ->exists();
    }
}
