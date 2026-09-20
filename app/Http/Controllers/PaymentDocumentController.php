<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentDocumentController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

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
