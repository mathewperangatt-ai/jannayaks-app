<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\InvoiceDeliveryService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentDocumentController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly InvoicePdfService $pdf,
        private readonly InvoiceDeliveryService $delivery,
    ) {}

    public function receipt(Request $request, Payment $payment): View|JsonResponse
    {
        $this->authorizeOwner($payment);
        $this->ensureSettled($payment);

        if ($payment->invoice_number === null || $payment->invoice_number === '') {
            $this->invoices->assignReceiptReference($payment);
            $payment->refresh();
        }

        $details = $this->invoices->receiptBillingDetails($payment);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'document' => $details]);
        }

        return view('payments.receipt', [
            'payment' => $payment,
            'document' => $details,
        ]);
    }

    public function taxInvoice(Request $request, Payment $payment): View|JsonResponse
    {
        $this->authorizeOwner($payment);
        $this->ensureSettled($payment);

        if ($payment->tax_invoice_number === null || $payment->tax_invoice_number === '') {
            $this->invoices->assignTaxInvoiceNumber($payment);
            $payment->refresh();
        }

        $details = $this->invoices->taxInvoiceDetails($payment);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'document' => $details]);
        }

        return view('payments.tax-invoice', [
            'payment' => $payment,
            'document' => $details,
        ]);
    }

    public function creditNote(Request $request, Payment $payment): View|JsonResponse
    {
        $this->authorizeOwner($payment);

        if (! $payment->isRefunded() && $payment->status !== Payment::STATUS_PARTIALLY_REFUNDED) {
            throw new NotFoundHttpException('Credit note is available only after a recorded refund.');
        }

        if ($payment->credit_note_number === null || $payment->credit_note_number === '') {
            $this->invoices->assignCreditNoteNumber($payment);
            $payment->refresh();
        }

        $details = $this->invoices->creditNoteDetails($payment);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'document' => $details]);
        }

        return view('payments.credit-note', [
            'payment' => $payment,
            'document' => $details,
        ]);
    }

    /**
     * Authenticated PDF download for the payment owner. The document number is
     * the stored, issued number; the PDF is regenerated on demand and served
     * from a non-guessable, login-gated route.
     */
    public function pdfDownload(Request $request, Payment $payment, string $kind): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $this->authorizeOwner($payment);

        try {
            $path = $this->pdf->store($payment, $kind);
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 409);
            }

            abort(404, $e->getMessage());
        }

        $absolute = \Illuminate\Support\Facades\Storage::disk('local')->path($path);

        return response()->file($absolute, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.basename($path).'"',
        ]);
    }

    /**
     * Finance-admin PDF download (PaymentPolicy::view via route authorization).
     */
    public function adminPdfDownload(Payment $payment, string $kind): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $this->authorizeFinance();

        try {
            $path = $this->pdf->store($payment, $kind);
        } catch (\Throwable $e) {
            abort(404, $e->getMessage());
        }

        $absolute = \Illuminate\Support\Facades\Storage::disk('local')->path($path);

        return response()->file($absolute, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.basename($path).'"',
        ]);
    }

    /**
     * Finance-admin resend of the settlement-document email. Explicit action:
     * re-sends regardless of invoice_emailed_at without reissuing numbers.
     */
    public function adminResendDocuments(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizeFinance();

        $sent = $this->delivery->sendSettlementDocuments($payment, force: true);

        return response()->json(['ok' => true, 'emailed' => $sent, 'invoice_emailed_at' => $payment->fresh()->invoice_emailed_at?->toIso8601String()]);
    }

    private function authorizeFinance(): void
    {
        $user = Auth::user();

        if ($user === null || ! $user->canManageFinance()) {
            throw new AccessDeniedHttpException('Finance administration is required.');
        }
    }

    private function authorizeOwner(Payment $payment): void
    {
        if (! Auth::check()) {
            throw new AccessDeniedHttpException('Login required.');
        }

        $userId = (int) Auth::id();

        $application = $payment->application;
        if ($application !== null && (int) $application->user_id === $userId) {
            return;
        }

        $profile = $payment->profile;
        if ($profile !== null && (int) $profile->user_id === $userId) {
            return;
        }

        $membershipProfile = $payment->membership?->profile;
        if ($membershipProfile !== null && (int) $membershipProfile->user_id === $userId) {
            return;
        }

        throw new AccessDeniedHttpException('This payment document is not yours.');
    }

    private function ensureSettled(Payment $payment): void
    {
        if (! $payment->isPaidOrBetter() && ! $payment->isRefunded()) {
            throw new NotFoundHttpException('Payment documents are available after settlement.');
        }
    }
}
