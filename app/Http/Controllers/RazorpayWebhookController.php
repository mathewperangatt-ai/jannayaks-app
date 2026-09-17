<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\ApplicationPaymentStateService;
use App\Services\InvoiceService;
use App\Services\RazorpayPaymentService;
use App\Services\RazorpayWebhookVerifier;
use App\Support\PricingAmounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RazorpayWebhookController extends Controller
{
    public function __construct(
        private readonly RazorpayWebhookVerifier $verifier,
        private readonly RazorpayPaymentService $razorpay,
        private readonly ApplicationPaymentStateService $stateService,
        private readonly InvoiceService $invoices,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        if (! is_string($rawPayload) || $rawPayload === '') {
            return response()->json(['ok' => false, 'error' => 'Empty payload.'], 400);
        }

        $signature = (string) $request->header(RazorpayWebhookVerifier::SIGNATURE_HEADER, '');
        $config = $this->razorpay->config();
        $webhookSecret = (string) ($config['webhook_secret'] ?? '');

        $signatureOk = $webhookSecret !== ''
            && $this->verifier->verify($rawPayload, $signature, $webhookSecret);

        if (! $signatureOk) {
            Log::warning('Razorpay webhook: invalid or missing signature.', [
                'has_signature_header' => $signature !== '',
                'has_webhook_secret' => $webhookSecret !== '',
            ]);

            return response()->json(['ok' => false, 'error' => 'Invalid signature.'], 403);
        }

        $payload = json_decode($rawPayload, true);
        if (! is_array($payload)) {
            return response()->json(['ok' => false, 'error' => 'Invalid JSON payload.'], 400);
        }

        $event = (string) ($payload['event'] ?? '');
        $eventId = (string) ($payload['event_id'] ?? '');
        $createdAt = isset($payload['created_at']) && is_numeric($payload['created_at'])
            ? (int) $payload['created_at']
            : null;

        if ($event === '') {
            return response()->json(['ok' => false, 'error' => 'Missing event.'], 400);
        }

        $paymentPayload = $payload['payload']['payment']['entity'] ?? null;
        $linkPayload = $payload['payload']['payment_link']['entity'] ?? null;

        $paymentId = null;
        $applicationId = null;
        $gatewayPaymentId = null;
        $gatewayLinkId = null;
        $orderId = null;
        $amountPaise = null;
        $currency = null;
        $notes = [];

        if (is_array($paymentPayload)) {
            $gatewayPaymentId = isset($paymentPayload['id']) ? (string) $paymentPayload['id'] : null;
            $orderId = isset($paymentPayload['order_id']) && $paymentPayload['order_id'] !== null ? (string) $paymentPayload['order_id'] : null;
            $amountPaise = isset($paymentPayload['amount']) ? (int) $paymentPayload['amount'] : null;
            $currency = isset($paymentPayload['currency']) ? (string) $paymentPayload['currency'] : null;
            if (isset($paymentPayload['notes']) && is_array($paymentPayload['notes'])) {
                $notes = $paymentPayload['notes'];
            }
        }
        if (is_array($linkPayload)) {
            $gatewayLinkId = isset($linkPayload['id']) ? (string) $linkPayload['id'] : null;
            if (isset($linkPayload['notes']) && is_array($linkPayload['notes']) && $notes === []) {
                $notes = $linkPayload['notes'];
            }
            if ($amountPaise === null && isset($linkPayload['amount'])) {
                $amountPaise = (int) $linkPayload['amount'];
            }
            if ($currency === null && isset($linkPayload['currency'])) {
                $currency = (string) $linkPayload['currency'];
            }
            if ($orderId === null && isset($linkPayload['order_id']) && $linkPayload['order_id'] !== null) {
                $orderId = (string) $linkPayload['order_id'];
            }
        }

        if (isset($notes['payment_id']) && is_numeric($notes['payment_id'])) {
            $paymentId = (int) $notes['payment_id'];
        }
        if (isset($notes['application_id']) && is_numeric($notes['application_id'])) {
            $applicationId = (int) $notes['application_id'];
        }

        $payment = $this->resolvePayment(
            paymentId: $paymentId,
            gatewayPaymentId: $gatewayPaymentId,
            gatewayLinkId: $gatewayLinkId,
            transactionRef: is_string($linkPayload['reference_id'] ?? null) ? (string) $linkPayload['reference_id'] : null,
            applicationId: $applicationId,
            event: $event,
        );

        if (! $payment instanceof Payment) {
            Log::warning('Razorpay webhook: could not resolve payment for event.', [
                'event' => $event,
                'event_id' => $eventId,
                'gateway_payment_id' => $gatewayPaymentId,
                'gateway_link_id' => $gatewayLinkId,
                'application_id' => $applicationId,
            ]);

            return response()->json([
                'ok' => true,
                'handled' => false,
                'reason' => 'payment_not_resolved',
            ], 202);
        }

        $eventTimestamp = $createdAt !== null ? Carbon::createFromTimestamp($createdAt) : null;

        $result = DB::transaction(function () use (
            $event,
            $eventId,
            $eventTimestamp,
            $payment,
            $gatewayPaymentId,
            $gatewayLinkId,
            $orderId,
            $amountPaise,
            $currency,
        ) {
            $payment = $payment->fresh() ?? $payment;

            if (! $this->checkAssociations($payment, $gatewayLinkId, $orderId)) {
                return ['status' => 409, 'body' => ['ok' => false, 'error' => 'Stale or mismatched link identifier.']];
            }

            if (! $this->checkAmountAndCurrency($payment, $amountPaise, $currency)) {
                return ['status' => 409, 'body' => ['ok' => false, 'error' => 'Amount or currency mismatch.']];
            }

            $duplicate = $this->isDuplicateEvent($payment, $eventId, $event);
            if ($duplicate) {
                return ['status' => 200, 'body' => ['ok' => true, 'idempotent' => true, 'payment_id' => $payment->id]];
            }

            return $this->applyEvent($event, $eventId, $eventTimestamp, $payment, $gatewayPaymentId, $gatewayLinkId, $orderId);
        });

        $status = $result['status'] ?? 200;
        $body = $result['body'] ?? ['ok' => true];

        return response()->json($body, $status);
    }

    private function resolvePayment(
        ?int $paymentId,
        ?string $gatewayPaymentId,
        ?string $gatewayLinkId,
        ?string $transactionRef,
        ?int $applicationId,
        string $event,
    ): ?Payment {
        $candidates = [];
        if ($paymentId !== null) {
            $candidates[] = Payment::query()->find($paymentId);
        }
        if ($gatewayPaymentId !== null && $gatewayPaymentId !== '') {
            $candidates[] = Payment::query()->where('gateway_payment_id', $gatewayPaymentId)->first();
        }
        if ($gatewayLinkId !== null && $gatewayLinkId !== '') {
            $candidates[] = Payment::query()->where('razorpay_link_id', $gatewayLinkId)->first();
        }
        if ($transactionRef !== null && $transactionRef !== '') {
            $candidates[] = Payment::query()->where('transaction_reference', $transactionRef)->first();
        }
        if ($applicationId !== null) {
            $q = Payment::query()->where('application_id', $applicationId);
            if (stripos($event, 'fail') !== false || stripos($event, 'cancel') !== false || stripos($event, 'expire') !== false) {
                $candidates[] = (clone $q)->latest('id')->first();
            } else {
                $candidates[] = (clone $q)->activeAttempts()->latest('id')->first();
            }
        }

        foreach ($candidates as $c) {
            if ($c instanceof Payment) {
                return $c;
            }
        }

        return null;
    }

    private function checkAssociations(Payment $payment, ?string $gatewayLinkId, ?string $orderId): bool
    {
        if ($gatewayLinkId !== null && $gatewayLinkId !== '' && $payment->razorpay_link_id !== null && $payment->razorpay_link_id !== '') {
            if ($gatewayLinkId !== $payment->razorpay_link_id) {
                return false;
            }
        }
        if ($orderId !== null && $orderId !== '' && $payment->razorpay_order_id !== null && $payment->razorpay_order_id !== '') {
            if ($orderId !== $payment->razorpay_order_id) {
                return false;
            }
        }

        return true;
    }

    private function checkAmountAndCurrency(Payment $payment, ?int $amountPaise, ?string $currency): bool
    {
        if ($currency !== null && $currency !== '') {
            if (! $payment->currencyIsInr()) {
                return false;
            }
            if (strtoupper($currency) !== PricingAmounts::CURRENCY) {
                return false;
            }
        }
        if ($amountPaise !== null) {
            $expected = $payment->totalPaise();
            if ($expected <= 0) {
                return true;
            }
            if (abs($amountPaise - $expected) > 1) {
                return false;
            }
        }

        return true;
    }

    private function isDuplicateEvent(Payment $payment, string $eventId, string $event): bool
    {
        if ($eventId === '') {
            return false;
        }
        if ($payment->gateway_event_id === $eventId) {
            return true;
        }

        return Payment::query()
            ->where('id', '!=', $payment->id)
            ->where(function ($q) use ($payment, $eventId) {
                $q->where('gateway_event_id', $eventId);
                if ($payment->application_id !== null) {
                    $q->orWhere('application_id', $payment->application_id);
                }
            })
            ->whereIn('status', [Payment::STATUS_PAID, Payment::STATUS_CAPTURED, Payment::STATUS_SUCCESS, Payment::STATUS_REFUNDED])
            ->exists();
    }

    private function applyEvent(
        string $event,
        string $eventId,
        ?Carbon $eventTimestamp,
        Payment $payment,
        ?string $gatewayPaymentId,
        ?string $gatewayLinkId,
        ?string $orderId,
    ): array {
        $successEvents = [
            'payment.captured',
            'payment.authorized',
            'payment.paid',
            'payment_link.paid',
            'order.paid',
        ];
        $failEvents = [
            'payment.failed',
            'payment_link.expired',
        ];

        $lower = strtolower($event);

        if (in_array($lower, $successEvents, true)) {
            if (! $payment->isActiveAttempt()) {
                return ['status' => 409, 'body' => [
                    'ok' => false,
                    'error' => 'Payment attempt is not active and cannot be settled.',
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                    'ignored_superseded' => true,
                ]];
            }

            $target = $lower === 'payment.captured' ? Payment::STATUS_CAPTURED : Payment::STATUS_PAID;
            if ($payment->status === Payment::STATUS_SUCCESS) {
                $target = Payment::STATUS_SUCCESS;
            }

            $payment->markPaidOrCaptured(
                targetStatus: $target,
                gatewayPaymentId: $gatewayPaymentId,
                gatewayEventId: $eventId !== '' ? $eventId : null,
                eventType: Payment::EVENT_PAYMENT_CAPTURED,
                paidAt: $eventTimestamp,
                capturedAt: $eventTimestamp,
            );

            if (! $payment->isPaidOrBetter()) {
                return ['status' => 409, 'body' => [
                    'ok' => false,
                    'error' => 'Payment attempt could not transition to settled.',
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                    'ignored_superseded' => true,
                ]];
            }
            if ($gatewayLinkId !== null && $gatewayLinkId !== '' && $payment->razorpay_link_id === null) {
                $payment->razorpay_link_id = $gatewayLinkId;
                $payment->save();
            }
            if ($orderId !== null && $orderId !== '' && $payment->razorpay_order_id === null) {
                $payment->razorpay_order_id = $orderId;
                $payment->save();
            }

            try {
                if ($payment->application_id !== null) {
                    $this->stateService->afterSettled($payment);
                }
            } catch (\Throwable $e) {
                Log::error('Razorpay webhook: afterSettled application update failed.', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $this->invoices->assignSettlementDocuments($payment);
            } catch (\Throwable $e) {
                Log::warning('Razorpay webhook: settlement document assignment failed.', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return ['status' => 200, 'body' => [
                'ok' => true,
                'paid' => true,
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'receipt_ref' => $payment->invoice_number,
                'tax_invoice_ref' => $payment->tax_invoice_number,
            ]];
        }

        if (in_array($lower, $failEvents, true)) {
            $target = $lower === 'payment_link.expired' ? Payment::STATUS_EXPIRED : Payment::STATUS_FAILED;
            $payment->markFailedExpiredOrCancelled(
                targetStatus: $target,
                gatewayEventId: $eventId !== '' ? $eventId : null,
                eventType: $target === Payment::STATUS_EXPIRED ? Payment::EVENT_PAYMENT_FAILED : Payment::EVENT_PAYMENT_FAILED,
            );

            return ['status' => 200, 'body' => [
                'ok' => true,
                'failed' => true,
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ]];
        }

        if ($lower === 'payment.cancelled') {
            $payment->markFailedExpiredOrCancelled(
                targetStatus: Payment::STATUS_CANCELLED,
                gatewayEventId: $eventId !== '' ? $eventId : null,
                eventType: Payment::EVENT_PAYMENT_FAILED,
            );

            return ['status' => 200, 'body' => [
                'ok' => true,
                'cancelled' => true,
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ]];
        }

        if ($lower === 'refund.processed') {
            if ($payment->isRefunded()) {
                return ['status' => 200, 'body' => ['ok' => true, 'idempotent' => true, 'payment_id' => $payment->id]];
            }
            if (! $payment->isPaidOrBetter()) {
                return ['status' => 409, 'body' => ['ok' => false, 'error' => 'Cannot refund non-settled payment.']];
            }
            $refundEntity = $GLOBALS['refund_payload_safe'] ?? null;

            return ['status' => 200, 'body' => ['ok' => true, 'ignored' => 'refund_state_only', 'payment_id' => $payment->id]];
        }

        return ['status' => 202, 'body' => [
            'ok' => true,
            'event_ignored' => true,
            'event' => $event,
            'payment_id' => $payment->id,
        ]];
    }
}
