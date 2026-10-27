<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use App\Support\PricingAmounts;
use Illuminate\Support\Facades\DB;
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

        // Eligibility ends the first time the customer is given the profile
        // to review. Creating a draft profile is not that event, and a later
        // preview invalidation does not reopen the refund.
        return $application->first_customer_preview_at === null;
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

    /**
     * Administrative local refund. This records the calculated refund and a
     * credit note. It does not call Razorpay. A second attempt is refused.
     * An ineligible attempt leaves the payment unchanged and is audited.
     */
    public function recordAdministrativeRefund(Payment $payment, User $actor, string $reason): Payment
    {
        $reason = trim($reason);

        try {
            if (! $actor->isAdmin() || ! $actor->isActiveAccount()) {
                throw new InvalidArgumentException('Only an authorised Admin may record a refund.');
            }
            if ($reason === '') {
                throw new InvalidArgumentException('A written reason is required to record a refund.');
            }

            return DB::transaction(function () use ($payment, $actor, $reason) {
                /** @var Payment $locked */
                $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

                if ($locked->isRefunded() || $locked->status === Payment::STATUS_PARTIALLY_REFUNDED) {
                    throw new InvalidArgumentException('A refund is already recorded for this payment.');
                }
                if ($locked->status === Payment::STATUS_WAIVED || $locked->gateway === Payment::GATEWAY_WAIVER) {
                    throw new InvalidArgumentException('A waiver cannot be refunded because no payment was collected.');
                }
                if (! $this->isEligible($locked)) {
                    throw new InvalidArgumentException('This payment is not eligible for a refund. No Razorpay refund was sent.');
                }

                $note = 'Local record only. Razorpay was not called. '.$reason;
                $recorded = $this->initiateRefund($locked, $note);
                $recorded->forceFill([
                    'event_type' => Payment::EVENT_REFUND_PROCESSED,
                ])->save();

                app(StaffAuditLogger::class)->log(
                    action: 'payment.refund_recorded',
                    subject: $recorded,
                    after: [
                        'disposition' => 'recorded_locally',
                        'refund_reason' => mb_substr($reason, 0, 255),
                        'refund_amount' => (string) $recorded->refund_amount,
                        'gateway_refund_executed' => false,
                        'application_id' => $recorded->application_id,
                    ],
                    actor: $actor,
                );

                return $recorded->fresh() ?? $recorded;
            });
        } catch (InvalidArgumentException $e) {
            app(StaffAuditLogger::class)->log(
                action: 'payment.refund_failed',
                subject: $payment,
                after: [
                    'disposition' => 'failed',
                    'refund_reason' => $reason !== '' ? mb_substr($reason, 0, 255) : null,
                    'failure' => $e->getMessage(),
                    'gateway_refund_executed' => false,
                    'payment_status' => $payment->status,
                ],
                actor: $actor,
            );

            throw $e;
        }
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
