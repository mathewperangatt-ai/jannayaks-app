<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class RazorpayCallbackController extends Controller
{
    public function show(Request $request): \Illuminate\View\View|\Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $paymentId = $request->input('payment_id');
        $razorpayPaymentId = $request->input('razorpay_payment_id');
        $razorpayLinkId = $request->input('razorpay_payment_link_id');
        $razorpayLinkRefId = $request->input('razorpay_payment_link_reference_id');
        $razorpayStatus = $request->input('razorpay_payment_link_status');

        $applicationId = null;
        $payment = null;
        if (is_numeric($paymentId)) {
            $payment = Payment::query()->find((int) $paymentId);
        }
        if (! $payment instanceof Payment && is_string($razorpayLinkId) && $razorpayLinkId !== '') {
            $payment = Payment::query()->where('razorpay_link_id', $razorpayLinkId)->first();
        }
        if (! $payment instanceof Payment && is_string($razorpayLinkRefId) && $razorpayLinkRefId !== '') {
            $payment = Payment::query()->where('transaction_reference', $razorpayLinkRefId)->first();
        }

        if ($payment instanceof Payment && $payment->application_id !== null) {
            $applicationId = (int) $payment->application_id;
        }

        $messages = [
            'paid'      => ['Payment is being verified. You will be updated once confirmation is received.',
                            'പേയ്‌മെൻ്റ് സ്ഥിരീകരിക്കുന്നു. സ്ഥിരീകരണം ലഭിച്ചാൽ നിങ്ങളെ അറിയിക്കും.'],
            'failed'    => ['Payment failed. You can retry from your application dashboard.',
                            'പേയ്‌മെൻ്റ് പരാജയപ്പെട്ടു. അപ്ലിക്കേഷൻ ഡാഷ്‌ബോർഡിൽ നിന്ന് വീണ്ടും ശ്രമിക്കാം.'],
            'cancelled' => ['Payment was cancelled. You can retry whenever ready.',
                            'പേയ്‌മെൻ്റ് റദ്ദാക്കി. തയ്യാറായാൽ വീണ്ടും ശ്രമിക്കാം.'],
            'expired'   => ['Payment link has expired. Please initiate a fresh payment.',
                            'പേയ്‌മെൻ്റ് ലിങ്ക് കാലഹരണപ്പെട്ടു. പുതിയ പേയ്‌മെൻ്റ് ആരംഭിക്കുക.'],
            'default'   => ['Payment verification is in progress. Please check back shortly.',
                            'പേയ്‌മെൻ്റ് സ്ഥിരീകരണം പ്രോഗ്രസ്സിൽ ആണ്. താൽക്കാലം പരിശോധിക്കുക.'],
        ];

        $key = 'default';
        if (is_string($razorpayStatus) && isset($messages[strtolower($razorpayStatus)])) {
            $key = strtolower($razorpayStatus);
        }

        $msgEn = $messages[$key][0];
        $msgMl = $messages[$key][1];

        if ($request->expectsJson()) {
            return response()->json([
                'ok'             => true,
                'verified'       => false,
                'callback_only'  => true,
                'message'        => $msgEn,
                'payment_status' => $payment?->status,
                'application_id' => $applicationId,
                'redirect_to'    => $applicationId !== null ? route('applications.payment', ['application' => $applicationId]) : route('home'),
            ]);
        }

        if ($applicationId !== null) {
            return redirect()
                ->route('applications.payment', ['application' => $applicationId])
                ->with('payment_callback_message_en', $msgEn)
                ->with('payment_callback_message_ml', $msgMl)
                ->with('payment_callback_status', $key);
        }

        return redirect()
            ->route('home')
            ->with('payment_callback_message_en', $msgEn)
            ->with('payment_callback_message_ml', $msgMl);
    }
}
