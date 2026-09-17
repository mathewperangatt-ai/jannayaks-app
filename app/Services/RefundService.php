<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Payment;
use App\Support\PricingAmounts;
use InvalidArgumentException;

class RefundService
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function refundBasisConfigPercent(): int
    {
        $cfg = config('jannayaks.refund.before_publication_percent', 60);
        $pct = (int) $cfg;
        if ($pct < 0) {
            $pct = 0;
        }
        if ($pct > 100) {
            $pct = 100;
        }

        return $pct;
    }

    public function isEligible(Payment $payment): bool
    {
        if (! $payment->isPaidOrBetter()) {
            return false;
        }
        if ($payment->isRefunded()) {
            return false;
        }

        $application = $payment->application;
        if (! $application instanceof Application) {
            return false;
        }

        $notYetPublished = $application->profile_id === null
            && $application->converted_to_profile_at === null;
        if (! $notYetPublished) {
            $statuses = (array) config('jannayaks.refund.refundable_statuses', ['pending', 'success']);

            return in_array((string) $application->status, $statuses, true);
        }

        return true;
    }

    public function calculateRefundAmountPaise(Payment $payment, ?int $overridePercent = null): ?int
    {
        if (! $this->isEligible($payment)) {
            return null;
        }

        $pct = $overridePercent !== null ? $overridePercent : $this->refundBasisConfigPercent();
        if ($pct < 0 || $pct > 100) {
            throw new InvalidArgumentException('Refund percent must be between 0 and 100.');
        }
        $totalPaise = $payment->totalPaise();
        if ($totalPaise <= 0) {
            return null;
        }

        $fraction = $pct / 100;
        $refundPaise = (int) round($totalPaise * $fraction);
        if ($refundPaise > $totalPaise) {
            $refundPaise = $totalPaise;
        }

        return $refundPaise;
    }

    /**
     * Record a refund locally and issue a credit note.
     * Does NOT call Razorpay (or any gateway) refund APIs.
     */
    public function initiateRefund(
        Payment $payment,
        string $note,
        ?int $overridePercent = null,
        ?string $refundGatewayId = null,
    ): ?Payment {
        if ($payment->isRefunded()) {
            if ($payment->credit_note_number === null || $payment->credit_note_number === '') {
                $this->invoices->assignCreditNoteNumber($payment);
            }

            return $payment->fresh() ?? $payment;
        }

        $refundPaise = $this->calculateRefundAmountPaise($payment, $overridePercent);
        if ($refundPaise === null || $refundPaise <= 0) {
            return null;
        }

        $refundDecimal = PricingAmounts::paiseToDecimalString($refundPaise);
        $sanitizedNote = trim($note);
        if ($sanitizedNote === '') {
            $sanitizedNote = 'Refund recorded (local foundation; gateway refund not executed in P8).';
        }

        $payment->markRefunded(
            refundAmount: $refundDecimal,
            refundGatewayId: $refundGatewayId,
            refundNote: $sanitizedNote,
        );

        $this->invoices->assignCreditNoteNumber($payment);

        $application = $payment->application;
        if ($application instanceof Application) {
            $application->forceFill([
                'payment_status' => Application::PAYMENT_STATUS_REFUNDED,
            ])->save();
        }

        return $payment->fresh() ?? $payment;
    }

    public function refundSummary(Payment $payment): array
    {
        $eligible = $this->isEligible($payment);
        $percent = $this->refundBasisConfigPercent();
        $refundPaise = $eligible ? $this->calculateRefundAmountPaise($payment) : null;
        $totalPaise = $payment->totalPaise();

        return [
            'eligible' => $eligible,
            'basis_percent' => $percent,
            'total_paid_paise' => $totalPaise,
            'total_paid_formatted' => PricingAmounts::formatMoneyInr($totalPaise),
            'refund_amount_paise' => $refundPaise,
            'refund_amount_formatted' => $refundPaise !== null ? PricingAmounts::formatMoneyInr($refundPaise) : null,
            'already_refunded' => $payment->isRefunded(),
            'refunded_at' => $payment->refunded_at,
            'refund_gateway_id' => $payment->refund_gateway_id,
            'refund_note' => $payment->refund_note,
            'credit_note_number' => $payment->credit_note_number,
            'gateway_refund_executed' => false,
        ];
    }
}
