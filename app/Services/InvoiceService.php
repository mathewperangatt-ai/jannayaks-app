<?php

namespace App\Services;

use App\Models\Payment;
use App\Support\PricingAmounts;
use Illuminate\Support\Str;
use RuntimeException;

class InvoiceService
{
    public const RECEIPT_PREFIX = 'JNK-RCPT-';

    public const INVOICE_PREFIX = 'JNK-INV-';

    public function assignReceiptReference(Payment $payment, bool $forceReassign = false): string
    {
        if (! $payment->isPaidOrBetter() && ! $payment->isRefunded()) {
            throw new RuntimeException('Cannot assign receipt reference to an unsettled payment.');
        }

        if (! $forceReassign && is_string($payment->invoice_number) && $payment->invoice_number !== '') {
            return $payment->invoice_number;
        }

        $attempts = 0;
        do {
            $ref = $this->generateReceiptReference();
            $exists = Payment::query()->where('invoice_number', $ref)->exists();
            $attempts++;
            if ($attempts > 20) {
                throw new RuntimeException('Unable to generate a unique receipt reference after 20 attempts.');
            }
        } while ($exists);

        $payment->invoice_number = $ref;
        if ($payment->invoice_issued_at === null) {
            $payment->invoice_issued_at = $payment->paid_at ?? now();
        }
        $payment->save();

        return $ref;
    }

    public function generateReceiptReference(): string
    {
        $datePart = now()->format('Ymd');
        $randPart = Str::upper(Str::random(7));

        return self::RECEIPT_PREFIX.$datePart.'-'.$randPart;
    }

    public function receiptBillingDetails(Payment $payment): array
    {
        $application = $payment->application;

        $billingName = null;
        $billingEmail = null;
        $billingMobile = null;
        if ($application !== null) {
            $name = trim((string) ($application->full_name ?? ''));
            if ($name !== '') {
                $billingName = $name;
            }
            $email = trim((string) ($application->preferred_contact_email ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $billingEmail = $email;
            }
            $mobile = trim((string) ($application->preferred_contact_mobile ?? ''));
            if ($mobile !== '') {
                $billingMobile = $mobile;
            }
        }

        $packageDescription = 'Profile package';
        if ($application !== null && in_array($application->package_tier, ['emerging', 'accomplished', 'distinguished'], true)) {
            $amt = PricingAmounts::forTier($application->package_tier);
            $packageDescription = $amt['label'] ?? $packageDescription;
        }

        return [
            'receipt_reference'      => $payment->invoice_number,
            'invoice_issued_at'      => $payment->invoice_issued_at,
            'payment_date'           => $payment->paid_at,
            'package_description'    => $packageDescription,
            'billing_name'           => $billingName,
            'billing_email'          => $billingEmail,
            'billing_mobile'         => $billingMobile,
            'currency'               => (string) $payment->currency,
            'total_amount_paise'     => $payment->totalPaise(),
            'total_amount_formatted' => PricingAmounts::formatMoneyInr($payment->totalPaise()),
            'base_amount_paise'      => $payment->base_amount !== null
                ? (int) round((float) (string) $payment->base_amount * PricingAmounts::PAISE_PER_RUPEE)
                : null,
            'gst_amount_paise'       => $this->sumGstPaise($payment),
            'gst_rate_percent'       => $payment->gst_rate_percent,
            'cgst_amount_paise'      => $payment->cgst_amount !== null
                ? (int) round((float) (string) $payment->cgst_amount * PricingAmounts::PAISE_PER_RUPEE)
                : null,
            'sgst_amount_paise'      => $payment->sgst_amount !== null
                ? (int) round((float) (string) $payment->sgst_amount * PricingAmounts::PAISE_PER_RUPEE)
                : null,
            'igst_amount_paise'      => $payment->igst_amount !== null
                ? (int) round((float) (string) $payment->igst_amount * PricingAmounts::PAISE_PER_RUPEE)
                : null,
            'transaction_reference'  => $payment->transaction_reference,
            'payment_gateway'        => $payment->gateway,
        ];
    }

    private function sumGstPaise(Payment $payment): ?int
    {
        $cols = ['cgst_amount', 'sgst_amount', 'igst_amount'];
        $total = 0;
        $any = false;
        foreach ($cols as $col) {
            $val = $payment->$col;
            if ($val !== null && is_numeric((string) $val)) {
                $total += (int) round((float) (string) $val * PricingAmounts::PAISE_PER_RUPEE);
                $any = true;
            }
        }

        return $any ? $total : null;
    }
}
