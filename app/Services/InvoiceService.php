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

    public const CREDIT_NOTE_PREFIX = 'JNK-CN-';

    public function assignSettlementDocuments(Payment $payment): Payment
    {
        $this->assignReceiptReference($payment);
        $this->assignTaxInvoiceNumber($payment);

        return $payment->fresh() ?? $payment;
    }

    public function assignReceiptReference(Payment $payment, bool $forceReassign = false): string
    {
        if (! $payment->isPaidOrBetter() && ! $payment->isRefunded()) {
            throw new RuntimeException('Cannot assign receipt reference to an unsettled payment.');
        }

        if (! $forceReassign && is_string($payment->invoice_number) && $payment->invoice_number !== '') {
            return $payment->invoice_number;
        }

        $ref = $this->generateUniqueReference(self::RECEIPT_PREFIX, 'invoice_number');
        $payment->invoice_number = $ref;
        if ($payment->invoice_issued_at === null) {
            $payment->invoice_issued_at = $payment->paid_at ?? now();
        }
        $payment->save();

        return $ref;
    }

    public function assignTaxInvoiceNumber(Payment $payment, bool $forceReassign = false): string
    {
        if (! $payment->isPaidOrBetter() && ! $payment->isRefunded()) {
            throw new RuntimeException('Cannot assign tax invoice number to an unsettled payment.');
        }

        if (! $forceReassign && is_string($payment->tax_invoice_number) && $payment->tax_invoice_number !== '') {
            return $payment->tax_invoice_number;
        }

        $ref = $this->generateUniqueReference(self::INVOICE_PREFIX, 'tax_invoice_number');
        $payment->tax_invoice_number = $ref;
        if ($payment->tax_invoice_issued_at === null) {
            $payment->tax_invoice_issued_at = $payment->paid_at ?? now();
        }
        $payment->save();

        return $ref;
    }

    public function assignCreditNoteNumber(Payment $payment, bool $forceReassign = false): string
    {
        if (! $payment->isRefunded() && $payment->status !== Payment::STATUS_PARTIALLY_REFUNDED) {
            throw new RuntimeException('Cannot assign credit note to a non-refunded payment.');
        }

        if (! $forceReassign && is_string($payment->credit_note_number) && $payment->credit_note_number !== '') {
            return $payment->credit_note_number;
        }

        $ref = $this->generateUniqueReference(self::CREDIT_NOTE_PREFIX, 'credit_note_number');
        $payment->credit_note_number = $ref;
        if ($payment->credit_note_issued_at === null) {
            $payment->credit_note_issued_at = $payment->refunded_at ?? now();
        }
        $payment->save();

        return $ref;
    }

    public function generateReceiptReference(): string
    {
        return $this->prefixedReference(self::RECEIPT_PREFIX);
    }

    public function generateTaxInvoiceReference(): string
    {
        return $this->prefixedReference(self::INVOICE_PREFIX);
    }

    public function generateCreditNoteReference(): string
    {
        return $this->prefixedReference(self::CREDIT_NOTE_PREFIX);
    }

    /**
     * @return array<string, mixed>
     */
    public function receiptBillingDetails(Payment $payment): array
    {
        return array_merge($this->commonDocumentDetails($payment), [
            'document_type' => 'payment_receipt',
            'document_title' => 'Payment Receipt',
            'document_number' => $payment->invoice_number,
            'document_issued_at' => $payment->invoice_issued_at,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function taxInvoiceDetails(Payment $payment): array
    {
        $seller = PricingAmounts::sellerBillingDetails();

        return array_merge($this->commonDocumentDetails($payment), [
            'document_type' => 'gst_tax_invoice',
            'document_title' => 'GST Tax Invoice',
            'document_number' => $payment->tax_invoice_number,
            'document_issued_at' => $payment->tax_invoice_issued_at,
            'seller_legal_name' => $seller['legal_name'] !== '' ? $seller['legal_name'] : null,
            'seller_gstin' => $seller['gstin'] !== '' ? $seller['gstin'] : null,
            'seller_address' => $seller['address'] !== '' ? $seller['address'] : null,
            'seller_state' => $seller['state'] !== '' ? $seller['state'] : null,
            'place_of_supply' => $seller['place_of_supply'] !== '' ? $seller['place_of_supply'] : null,
            'seller_support_email' => $seller['support_email'] !== '' ? $seller['support_email'] : null,
            'seller_details_configured' => $seller['legal_name'] !== '' || $seller['gstin'] !== '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function creditNoteDetails(Payment $payment): array
    {
        $refundPaise = 0;
        if ($payment->refund_amount !== null && is_numeric((string) $payment->refund_amount)) {
            $refundPaise = (int) round((float) (string) $payment->refund_amount * PricingAmounts::PAISE_PER_RUPEE);
        }

        return array_merge($this->commonDocumentDetails($payment), [
            'document_type' => 'credit_note',
            'document_title' => 'Refund Credit Note',
            'document_number' => $payment->credit_note_number,
            'document_issued_at' => $payment->credit_note_issued_at,
            'original_receipt_number' => $payment->invoice_number,
            'original_tax_invoice_number' => $payment->tax_invoice_number,
            'refund_amount_paise' => $refundPaise,
            'refund_amount_formatted' => PricingAmounts::formatMoneyInr($refundPaise),
            'refund_note' => $payment->refund_note,
            'refunded_at' => $payment->refunded_at,
            'refund_percent_basis' => (int) config('jannayaks.refund.before_publication_percent', 60),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function commonDocumentDetails(Payment $payment): array
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
        $includesAddon = false;
        if ($application !== null && in_array($application->package_tier, ['emerging', 'accomplished', 'distinguished'], true)) {
            $amt = PricingAmounts::forApplicationPackage(
                $application->package_tier,
                (bool) $application->distinguished_interview_addon,
            );
            $packageDescription = $amt['label'] ?? $packageDescription;
            $includesAddon = (bool) ($amt['includes_addon'] ?? false);
        }

        return [
            'receipt_reference' => $payment->invoice_number,
            'tax_invoice_number' => $payment->tax_invoice_number,
            'invoice_issued_at' => $payment->invoice_issued_at,
            'payment_date' => $payment->paid_at,
            'package_description' => $packageDescription,
            'includes_distinguished_addon' => $includesAddon,
            'billing_name' => $billingName,
            'billing_email' => $billingEmail,
            'billing_mobile' => $billingMobile,
            'currency' => (string) $payment->currency,
            'total_amount_paise' => $payment->totalPaise(),
            'total_amount_formatted' => PricingAmounts::formatMoneyInr($payment->totalPaise()),
            'base_amount_paise' => $payment->base_amount !== null
                ? (int) round((float) (string) $payment->base_amount * PricingAmounts::PAISE_PER_RUPEE)
                : null,
            'gst_amount_paise' => $this->sumGstPaise($payment),
            'gst_rate_percent' => $payment->gst_rate_percent,
            'cgst_amount_paise' => $payment->cgst_amount !== null
                ? (int) round((float) (string) $payment->cgst_amount * PricingAmounts::PAISE_PER_RUPEE)
                : null,
            'sgst_amount_paise' => $payment->sgst_amount !== null
                ? (int) round((float) (string) $payment->sgst_amount * PricingAmounts::PAISE_PER_RUPEE)
                : null,
            'igst_amount_paise' => $payment->igst_amount !== null
                ? (int) round((float) (string) $payment->igst_amount * PricingAmounts::PAISE_PER_RUPEE)
                : null,
            'transaction_reference' => $payment->transaction_reference,
            'payment_gateway' => $payment->gateway,
            'gateway_payment_id' => $payment->gateway_payment_id,
            'razorpay_order_id' => $payment->razorpay_order_id,
            'payment_status' => $payment->status,
            'application_id' => $payment->application_id,
        ];
    }

    private function generateUniqueReference(string $prefix, string $column): string
    {
        $attempts = 0;
        do {
            $ref = $this->prefixedReference($prefix);
            $exists = Payment::query()->where($column, $ref)->exists();
            $attempts++;
            if ($attempts > 20) {
                throw new RuntimeException('Unable to generate a unique '.$column.' after 20 attempts.');
            }
        } while ($exists);

        return $ref;
    }

    private function prefixedReference(string $prefix): string
    {
        $datePart = now()->format('Ymd');
        $randPart = Str::upper(Str::random(7));

        return $prefix.$datePart.'-'.$randPart;
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
