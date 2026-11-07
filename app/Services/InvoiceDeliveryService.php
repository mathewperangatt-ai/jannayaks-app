<?php

namespace App\Services;

use App\Mail\PaymentDocumentMail;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails settlement documents to the customer's registered address after a
 * trusted server-side payment confirmation. Delivery is idempotent per
 * payment (invoice_emailed_at) and deliberately non-fatal: a delivery failure
 * never invalidates the issued documents, and the payment keeps
 * invoice_emailed_at = null so the send can be retried (admin resend).
 */
class InvoiceDeliveryService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly InvoicePdfService $pdf,
    ) {}

    /**
     * Send after settlement. Returns true when an email was dispatched now.
     */
    public function sendSettlementDocuments(Payment $payment, bool $force = false): bool
    {
        if (! $force && $payment->invoice_emailed_at !== null) {
            return false;
        }

        if (! (bool) config('jannayaks.tier_pricing.billing.document_emails', true)) {
            return false;
        }

        $to = trim((string) ($payment->application?->preferred_contact_email ?? ''));
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $mail = new PaymentDocumentMail($payment, $this->attachments($payment));
            Mail::to($to)->send($mail);
        } catch (\Throwable $e) {
            // Delivery failure keeps the issued documents intact and retryable.
            Log::warning('Settlement document email failed.', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($payment->invoice_emailed_at === null) {
            $payment->forceFill(['invoice_emailed_at' => now()])->save();
        }

        return true;
    }

    /**
     * @return array<string, array{bytes: string, filename: string}>
     */
    public function attachments(Payment $payment): array
    {
        $attachments = [];

        if ($payment->isPaidOrBetter()) {
            try {
                $attachments['receipt'] = $this->pdf->build($payment, InvoicePdfService::KIND_RECEIPT);
            } catch (\Throwable $e) {
                Log::warning('Receipt PDF build failed for email.', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            }

            try {
                $attachments['tax_invoice'] = $this->pdf->build($payment, InvoicePdfService::KIND_TAX_INVOICE);
            } catch (\Throwable $e) {
                // Unconfigured statutory details: no invalid tax invoice is sent.
                Log::info('Tax invoice PDF not issued for email.', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            }
        }

        if ($payment->isRefunded() || $payment->status === Payment::STATUS_PARTIALLY_REFUNDED) {
            try {
                $attachments['credit_note'] = $this->pdf->build($payment, InvoicePdfService::KIND_CREDIT_NOTE);
            } catch (\Throwable $e) {
                Log::warning('Credit note PDF build failed for email.', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            }
        }

        return $attachments;
    }
}
