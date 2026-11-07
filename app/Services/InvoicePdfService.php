<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Renders settlement documents (payment receipt, GST tax invoice, refund
 * credit note) as print-ready PDFs with Jannayaks branding, and stores them
 * on the private local disk. Bytes are only produced for settled payments
 * and only when the statutory configuration for the requested document exists:
 * a GST tax invoice without configured seller details is refused rather than
 * issued invalid, while the payment receipt remains available.
 */
class InvoicePdfService
{
    public const KIND_RECEIPT = 'receipt';

    public const KIND_TAX_INVOICE = 'tax-invoice';

    public const KIND_CREDIT_NOTE = 'credit-note';

    public function __construct(private readonly InvoiceService $invoices) {}

    /**
     * @return array{bytes: string, filename: string}
     */
    public function build(Payment $payment, string $kind): array
    {
        $details = $this->documentDetails($payment, $kind);

        $options = new \Dompdf\Options([
            'isRemoteEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
        ]);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml(view('payments.pdf.document', [
            'payment' => $payment,
            'document' => $details,
        ])->render(), 'UTF-8');
        $dompdf->setPaper('a4');
        $dompdf->render();

        return [
            'bytes' => (string) $dompdf->output(),
            'filename' => $this->filename($payment, $details),
        ];
    }

    /**
     * Store the PDF on the private local disk and return the stored path.
     * Regenerating for the same payment and document number overwrites in
     * place; issued document numbers are never changed by this service.
     */
    public function store(Payment $payment, string $kind): string
    {
        $built = $this->build($payment, $kind);
        $path = $this->storedPath($payment, $built['filename']);
        Storage::disk('local')->put($path, $built['bytes']);

        return $path;
    }

    public function storedPath(Payment $payment, string $filename): string
    {
        return 'invoices/payment-'.$payment->id.'/'.$filename;
    }

    /**
     * @return array<string, mixed>
     */
    public function documentDetails(Payment $payment, string $kind): array
    {
        if (! $payment->isPaidOrBetter() && ! $payment->isRefunded()) {
            throw new RuntimeException('Documents are only available for settled payments.');
        }

        return match ($kind) {
            self::KIND_RECEIPT => $this->requireNumber($this->invoices->receiptBillingDetails($payment), $this->invoices->assignReceiptReference($payment), 'invoice_number', $payment),
            self::KIND_TAX_INVOICE => $this->taxInvoiceOrRefuse($payment),
            self::KIND_CREDIT_NOTE => $this->requireNumber($this->invoices->creditNoteDetails($payment), $payment->credit_note_number ?: $this->invoices->assignCreditNoteNumber($payment), 'credit_note_number', $payment),
            default => throw new \InvalidArgumentException('Unknown document kind.'),
        };
    }

    public function isAvailable(Payment $payment, string $kind): bool
    {
        try {
            $this->documentDetails($payment, $kind);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function taxInvoiceOrRefuse(Payment $payment): array
    {
        $details = $this->invoices->taxInvoiceDetails($payment);

        // A GST tax invoice must not be issued with unapproved statutory details.
        if (empty($details['seller_details_configured'])) {
            throw new RuntimeException('Tax invoice seller details are not configured.');
        }

        return $this->requireNumber($details, $payment->tax_invoice_number ?: $this->invoices->assignTaxInvoiceNumber($payment), 'tax_invoice_number', $payment);
    }

    private function requireNumber(array $details, string $number, string $column, Payment $payment): array
    {
        if ($number === '' || $number === null) {
            $number = (string) ($payment->$column ?? '');
        }

        if ($number === '') {
            throw new RuntimeException('Document number has not been assigned.');
        }

        $details['document_number'] = $number;

        return $details;
    }

    private function filename(Payment $payment, array $details): string
    {
        $number = (string) ($details['document_number'] ?? 'document');
        $slug = Str::slug($number);

        return $slug !== '' ? $slug.'.pdf' : 'jannayaks-document-'.$payment->id.'.pdf';
    }
}
