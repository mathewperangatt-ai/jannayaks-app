<?php

namespace App\Services;

use App\Exceptions\PaymentLinkStateUncertainException;
use App\Models\Application;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\User;
use App\Support\PricingAmounts;
use Illuminate\Support\Facades\Log;
use DateTimeInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class RazorpayPaymentService
{
    public const RAZORPAY_LINKS_ENDPOINT = 'https://api.razorpay.com/v1/payment_links';

    public const TEST_KEY_PREFIX = 'rzp_test_';

    public const LIVE_KEY_PREFIX = 'rzp_live_';

    public const RECONCILE_READY = 'ready';

    public const RECONCILE_CAPTURED = 'captured';

    public const RECONCILE_UNCERTAIN = 'uncertain';

    public const CODE_SETTLED_ON_WITHDRAWN_LINK = 'SETTLED_ON_WITHDRAWN_LINK';

    public const CODE_DUPLICATE_CAPTURE = 'DUPLICATE_CAPTURE_NEEDS_RECONCILIATION';

    public const CODE_RECONCILIATION_UNRESOLVED = 'CAPTURE_RECONCILIATION_UNRESOLVED';

    public const CODE_RECONCILIATION_MISMATCH = 'CAPTURE_RECONCILIATION_MISMATCH';

    public const CODE_ADDITIONAL_CAPTURE = 'ADDITIONAL_CAPTURE_NEEDS_RECONCILIATION';

    public const CODE_LINK_CLOSED = 'GATEWAY_LINK_CONFIRMED_CLOSED';

    public const OUTCOME_SETTLED = 'settled';

    public const OUTCOME_ALREADY_SETTLED = 'already_settled';

    public const OUTCOME_DUPLICATE = 'duplicate';

    public const OUTCOME_CLOSED = 'closed';

    public const OUTCOME_MISMATCH = 'mismatch';

    public const OUTCOME_UNRESOLVED = 'unresolved';

    public const OUTCOME_PENDING = 'pending';

    public const OUTCOME_UNAVAILABLE = 'unavailable';

    public const OUTCOME_SKIPPED = 'skipped';

    public const OUTCOME_DISABLED = 'disabled';

    public const OUTCOME_STALE = 'stale_claim';

    public function createApplicationPaymentLink(Application $application): Payment
    {
        if (! in_array($application->package_tier, ['emerging', 'accomplished', 'distinguished'], true)) {
            throw new InvalidArgumentException('Invalid application package tier.');
        }

        $amounts = PricingAmounts::forApplicationPackage($application->package_tier);
        PricingAmounts::assertInr($amounts['currency']);

        $totalPaise = (int) $amounts['amount_incl_paise'];
        if ($totalPaise <= 0) {
            throw new RuntimeException('Payment amount must be positive.');
        }

        return DB::transaction(function () use ($application, $amounts, $totalPaise) {
            $actives = Payment::query()
                ->where('application_id', $application->id)
                ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_INITIATED])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $reusable = null;
            $capturedAttempt = null;
            $blockedByUncertainLink = false;
            foreach ($actives as $active) {
                if ($active->totalPaise() === $totalPaise && $reusable === null) {
                    $reusable = $active;

                    continue;
                }

                $outcome = $this->reconcileStaleAttempt($active, 'Superseded by a newer payment attempt with a different amount or configuration.');
                if ($outcome === self::RECONCILE_CAPTURED) {
                    $capturedAttempt = $active->fresh() ?? $active;
                } elseif ($outcome === self::RECONCILE_UNCERTAIN) {
                    $blockedByUncertainLink = true;
                }
            }

            if ($capturedAttempt instanceof Payment) {
                return $capturedAttempt;
            }

            if ($blockedByUncertainLink) {
                throw new PaymentLinkStateUncertainException(
                    'An earlier payment link is still being confirmed. A new link cannot be started yet.'
                );
            }

            if ($reusable instanceof Payment
                && $reusable->razorpay_link_id !== null
                && $reusable->razorpay_link_url !== null
                && $reusable->totalPaise() === $totalPaise) {
                return $reusable;
            }

            if ($reusable instanceof Payment) {
                $payment = $reusable;
                $payment->forceFill([
                    'amount' => PricingAmounts::paiseToDecimalString($totalPaise),
                    'currency' => PricingAmounts::CURRENCY,
                    'base_amount' => PricingAmounts::paiseToDecimalString((int) $amounts['base_paise']),
                    'taxable_amount' => PricingAmounts::paiseToDecimalString((int) $amounts['base_paise']),
                    'gst_rate_percent' => $amounts['gst_rate_percent'],
                    'cgst_amount' => $amounts['cgst_paise'] !== null ? PricingAmounts::paiseToDecimalString((int) $amounts['cgst_paise']) : null,
                    'sgst_amount' => $amounts['sgst_paise'] !== null ? PricingAmounts::paiseToDecimalString((int) $amounts['sgst_paise']) : null,
                    'igst_amount' => $amounts['igst_paise'] !== null ? PricingAmounts::paiseToDecimalString((int) $amounts['igst_paise']) : null,
                    'event_type' => 'application_package',
                ])->save();
            } else {
                $txnRef = 'JNK-PAY-'.$application->id.'-'.Str::upper(Str::random(10));
                $payment = Payment::query()->create([
                    'application_id' => $application->id,
                    'transaction_reference' => $txnRef,
                    'gateway' => Payment::GATEWAY_RAZORPAY,
                    'item_type' => Payment::ITEM_APPLICATION_PAYMENT,
                    'amount' => PricingAmounts::paiseToDecimalString($totalPaise),
                    'currency' => PricingAmounts::CURRENCY,
                    'status' => Payment::STATUS_PENDING,
                    'event_type' => 'application_package',
                    'base_amount' => PricingAmounts::paiseToDecimalString((int) $amounts['base_paise']),
                    'taxable_amount' => PricingAmounts::paiseToDecimalString((int) $amounts['base_paise']),
                    'gst_rate_percent' => $amounts['gst_rate_percent'],
                    'cgst_amount' => $amounts['cgst_paise'] !== null ? PricingAmounts::paiseToDecimalString((int) $amounts['cgst_paise']) : null,
                    'sgst_amount' => $amounts['sgst_paise'] !== null ? PricingAmounts::paiseToDecimalString((int) $amounts['sgst_paise']) : null,
                    'igst_amount' => $amounts['igst_paise'] !== null ? PricingAmounts::paiseToDecimalString((int) $amounts['igst_paise']) : null,
                ]);
            }

            $config = $this->config();
            if (! $config['enabled']) {
                $payment->markInitiated(Payment::GATEWAY_RAZORPAY);

                return $payment->fresh() ?? $payment;
            }

            $payload = $this->buildLinkPayload($application, $payment, $amounts, $totalPaise);
            $response = $this->http()->post(self::RAZORPAY_LINKS_ENDPOINT, $payload);

            if (! $response->successful()) {
                $body = $response->json();
                $errMsg = is_array($body) && isset($body['error']['description'])
                    ? (string) $body['error']['description']
                    : 'Razorpay link creation failed (HTTP '.$response->status().').';
                $errCode = is_array($body) && isset($body['error']['code']) ? (string) $body['error']['code'] : 'HTTP_'.$response->status();
                $payment->markFailedExpiredOrCancelled(
                    Payment::STATUS_FAILED,
                    $errCode,
                    $errMsg,
                );
                throw new RuntimeException($errMsg);
            }

            $data = $response->json();
            if (! is_array($data) || ! isset($data['id']) || ! isset($data['short_url'])) {
                $payment->markFailedExpiredOrCancelled(
                    Payment::STATUS_FAILED,
                    'MALFORMED_RESPONSE',
                    'Razorpay response missing id or short_url.',
                );
                throw new RuntimeException('Malformed Razorpay payment link response.');
            }

            $payment->razorpay_link_id = (string) $data['id'];
            $payment->razorpay_link_url = (string) $data['short_url'];
            if (isset($data['order_id']) && is_string($data['order_id'])) {
                $payment->razorpay_order_id = (string) $data['order_id'];
            }
            $payment->markInitiated(Payment::GATEWAY_RAZORPAY);

            return $payment->fresh() ?? $payment;
        });
    }

    /**
     * Create (or reuse) an annual membership renewal payment link for the profile owner.
     * Does not activate/renew membership — webhook settlement remains authoritative.
     */
    public function createMembershipRenewalPaymentLink(Membership $membership, User $actor): Payment
    {
        $membership->loadMissing('profile');
        $profile = $membership->profile;
        if ($profile === null) {
            throw new InvalidArgumentException('Membership has no linked profile.');
        }

        if ((int) $profile->user_id !== (int) $actor->id && ! $actor->isAdmin()) {
            throw new InvalidArgumentException('You may only renew your own membership.');
        }

        $lifecycle = app(MembershipLifecycleService::class);
        if (! $lifecycle->isWithinRetention($membership) && $lifecycle->isPastGracePeriod($membership)) {
            throw new InvalidArgumentException('This membership is outside the retention window and cannot be renewed here.');
        }

        $amounts = PricingAmounts::forTierRenewal(PricingAmounts::tierKeyForMembership($membership));
        PricingAmounts::assertInr($amounts['currency']);
        $totalPaise = (int) $amounts['amount_incl_paise'];
        if ($totalPaise <= 0) {
            throw new RuntimeException('Renewal payment amount must be positive.');
        }

        return DB::transaction(function () use ($membership, $profile, $amounts, $totalPaise) {
            /** @var Membership $locked */
            $locked = Membership::query()->whereKey($membership->id)->lockForUpdate()->firstOrFail();

            $actives = Payment::query()
                ->where('membership_id', $locked->id)
                ->where('item_type', Payment::ITEM_MEMBERSHIP)
                ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_INITIATED])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $reusable = null;
            $capturedAttempt = null;
            $blockedByUncertainLink = false;
            foreach ($actives as $active) {
                if ($active->totalPaise() === $totalPaise && $reusable === null) {
                    $reusable = $active;

                    continue;
                }

                $outcome = $this->reconcileStaleAttempt($active, 'Superseded by a newer membership renewal attempt.');
                if ($outcome === self::RECONCILE_CAPTURED) {
                    $capturedAttempt = $active->fresh() ?? $active;
                } elseif ($outcome === self::RECONCILE_UNCERTAIN) {
                    $blockedByUncertainLink = true;
                }
            }

            if ($capturedAttempt instanceof Payment) {
                return $capturedAttempt;
            }

            if ($blockedByUncertainLink) {
                throw new PaymentLinkStateUncertainException(
                    'An earlier renewal link is still being confirmed. A new link cannot be started yet.'
                );
            }

            if ($reusable instanceof Payment
                && $reusable->razorpay_link_id !== null
                && $reusable->razorpay_link_url !== null
                && $reusable->totalPaise() === $totalPaise) {
                return $reusable;
            }

            if ($reusable instanceof Payment) {
                $payment = $reusable;
                $payment->forceFill([
                    'amount' => PricingAmounts::paiseToDecimalString($totalPaise),
                    'currency' => PricingAmounts::CURRENCY,
                    'base_amount' => PricingAmounts::paiseToDecimalString((int) $amounts['base_paise']),
                    'taxable_amount' => PricingAmounts::paiseToDecimalString((int) $amounts['base_paise']),
                    'gst_rate_percent' => $amounts['gst_rate_percent'],
                    'cgst_amount' => $amounts['cgst_paise'] !== null ? PricingAmounts::paiseToDecimalString((int) $amounts['cgst_paise']) : null,
                    'sgst_amount' => $amounts['sgst_paise'] !== null ? PricingAmounts::paiseToDecimalString((int) $amounts['sgst_paise']) : null,
                    'igst_amount' => $amounts['igst_paise'] !== null ? PricingAmounts::paiseToDecimalString((int) $amounts['igst_paise']) : null,
                    'event_type' => 'renewal',
                    'profile_id' => $profile->id,
                ])->save();
            } else {
                $txnRef = 'JNK-MEM-'.$locked->id.'-'.Str::upper(Str::random(10));
                $payment = Payment::query()->create([
                    'membership_id' => $locked->id,
                    'profile_id' => $profile->id,
                    'transaction_reference' => $txnRef,
                    'gateway' => Payment::GATEWAY_RAZORPAY,
                    'item_type' => Payment::ITEM_MEMBERSHIP,
                    'amount' => PricingAmounts::paiseToDecimalString($totalPaise),
                    'currency' => PricingAmounts::CURRENCY,
                    'status' => Payment::STATUS_PENDING,
                    'event_type' => 'renewal',
                    'base_amount' => PricingAmounts::paiseToDecimalString((int) $amounts['base_paise']),
                    'taxable_amount' => PricingAmounts::paiseToDecimalString((int) $amounts['base_paise']),
                    'gst_rate_percent' => $amounts['gst_rate_percent'],
                    'cgst_amount' => $amounts['cgst_paise'] !== null ? PricingAmounts::paiseToDecimalString((int) $amounts['cgst_paise']) : null,
                    'sgst_amount' => $amounts['sgst_paise'] !== null ? PricingAmounts::paiseToDecimalString((int) $amounts['sgst_paise']) : null,
                    'igst_amount' => $amounts['igst_paise'] !== null ? PricingAmounts::paiseToDecimalString((int) $amounts['igst_paise']) : null,
                ]);
            }

            if ($locked->status === 'active') {
                $locked->forceFill(['status' => 'pending_renewal'])->save();
            }

            $config = $this->config();
            if (! $config['enabled']) {
                $payment->markInitiated(Payment::GATEWAY_RAZORPAY);

                return $payment->fresh() ?? $payment;
            }

            $payload = $this->buildMembershipRenewalLinkPayload($locked, $payment, $amounts, $totalPaise);
            $response = $this->http()->post(self::RAZORPAY_LINKS_ENDPOINT, $payload);

            if (! $response->successful()) {
                $body = $response->json();
                $errMsg = is_array($body) && isset($body['error']['description'])
                    ? (string) $body['error']['description']
                    : 'Razorpay link creation failed (HTTP '.$response->status().').';
                $errCode = is_array($body) && isset($body['error']['code']) ? (string) $body['error']['code'] : 'HTTP_'.$response->status();
                $payment->markFailedExpiredOrCancelled(
                    Payment::STATUS_FAILED,
                    $errCode,
                    $errMsg,
                );
                throw new RuntimeException($errMsg);
            }

            $data = $response->json();
            if (! is_array($data) || ! isset($data['id']) || ! isset($data['short_url'])) {
                $payment->markFailedExpiredOrCancelled(
                    Payment::STATUS_FAILED,
                    'MALFORMED_RESPONSE',
                    'Razorpay response missing id or short_url.',
                );
                throw new RuntimeException('Malformed Razorpay payment link response.');
            }

            $payment->razorpay_link_id = (string) $data['id'];
            $payment->razorpay_link_url = (string) $data['short_url'];
            if (isset($data['order_id']) && is_string($data['order_id'])) {
                $payment->razorpay_order_id = (string) $data['order_id'];
            }
            $payment->markInitiated(Payment::GATEWAY_RAZORPAY);

            return $payment->fresh() ?? $payment;
        });
    }

    /**
     * Withdraw a stale attempt only when the gateway confirms it is dead,
     * or when no gateway link was ever issued. A failed or timed-out
     * gateway check leaves the attempt active.
     */
    public function supersedeAttempt(Payment $payment, string $reason = 'Superseded by a newer payment attempt.'): void
    {
        $outcome = $this->reconcileStaleAttempt($payment, $reason);
        if ($outcome === self::RECONCILE_UNCERTAIN) {
            throw new PaymentLinkStateUncertainException(
                'An earlier payment link is still being confirmed. A new link cannot be started yet.'
            );
        }
    }

    public function cancelLink(Payment $payment): void
    {
        $this->supersedeAttempt($payment, 'Payment attempt cancelled.');
    }

    public function reconcileStaleAttempt(Payment $payment, string $reason): string
    {
        if ($payment->isSettled()) {
            return self::RECONCILE_CAPTURED;
        }

        if ($payment->status === Payment::STATUS_CANCELLED) {
            return self::RECONCILE_READY;
        }

        $linkId = trim((string) $payment->razorpay_link_id);
        if ($linkId === '') {
            $this->markConfirmedWithdrawn($payment, $reason);

            return self::RECONCILE_READY;
        }

        if (! $this->config()['enabled']) {
            return self::RECONCILE_UNCERTAIN;
        }

        $snapshot = $this->fetchPaymentLink($linkId);
        if ($snapshot === null) {
            return self::RECONCILE_UNCERTAIN;
        }

        $status = strtolower((string) ($snapshot['status'] ?? ''));
        if (in_array($status, ['cancelled', 'expired'], true)) {
            $this->markConfirmedWithdrawn($payment, $reason);

            return self::RECONCILE_READY;
        }

        if ($status === 'paid') {
            return $this->recordGatewayPaidLink($payment, $snapshot) ? self::RECONCILE_CAPTURED : self::RECONCILE_UNCERTAIN;
        }

        $cancelled = $this->requestLinkCancellation($linkId);
        if ($cancelled === null) {
            return self::RECONCILE_UNCERTAIN;
        }

        $cancelledStatus = strtolower((string) ($cancelled['status'] ?? ''));
        if (in_array($cancelledStatus, ['cancelled', 'expired'], true)) {
            $this->markConfirmedWithdrawn($payment, $reason);

            return self::RECONCILE_READY;
        }

        if ($cancelledStatus === 'paid') {
            return $this->recordGatewayPaidLink($payment, $cancelled) ? self::RECONCILE_CAPTURED : self::RECONCILE_UNCERTAIN;
        }

        $refetched = $this->fetchPaymentLink($linkId);
        if ($refetched === null) {
            return self::RECONCILE_UNCERTAIN;
        }

        $refetchedStatus = strtolower((string) ($refetched['status'] ?? ''));
        if (in_array($refetchedStatus, ['cancelled', 'expired'], true)) {
            $this->markConfirmedWithdrawn($payment, $reason);

            return self::RECONCILE_READY;
        }

        if ($refetchedStatus === 'paid') {
            return $this->recordGatewayPaidLink($payment, $refetched) ? self::RECONCILE_CAPTURED : self::RECONCILE_UNCERTAIN;
        }

        return self::RECONCILE_UNCERTAIN;
    }

    /**
     * Record a verified capture against this attempt. A withdrawn (cancelled)
     * attempt may settle only through Payment::settleWithdrawnCapture().
     * A second settled payment for the same application or membership is
     * stored and flagged, without another unlock or service invoice.
     */
    public function recordVerifiedCapture(
        Payment $payment,
        string $gatewayPaymentId,
        ?string $gatewayEventId = null,
        ?DateTimeInterface $paidAt = null,
    ): Payment {
        if (! $this->isPlausibleGatewayPaymentId($gatewayPaymentId)) {
            return $payment;
        }

        if ($payment->isPaidOrBetter()) {
            return $payment;
        }

        $duplicate = $this->anotherSettledPaymentExists($payment);
        $paidAt = $paidAt ?? now();

        if ($payment->isLateCaptureTerminal()) {
            $payment->settleWithdrawnCapture(
                targetStatus: Payment::STATUS_CAPTURED,
                gatewayPaymentId: $gatewayPaymentId,
                gatewayEventId: $gatewayEventId,
                paidAt: $paidAt,
            );
        } elseif ($payment->isActiveAttempt()) {
            $payment->markPaidOrCaptured(
                targetStatus: Payment::STATUS_CAPTURED,
                gatewayPaymentId: $gatewayPaymentId,
                gatewayEventId: $gatewayEventId,
                eventType: Payment::EVENT_PAYMENT_CAPTURED,
                paidAt: $paidAt,
                capturedAt: $paidAt,
            );
        } else {
            return $payment;
        }

        if (! $payment->isPaidOrBetter()) {
            return $payment;
        }

        if ($payment->razorpay_link_url !== null) {
            $payment->razorpay_link_url = null;
        }

        $payment->error_code = $duplicate ? self::CODE_DUPLICATE_CAPTURE : self::CODE_SETTLED_ON_WITHDRAWN_LINK;
        $payment->error_message = mb_substr(
            $duplicate
                ? 'Verified capture recorded after another payment had already settled. Staff reconciliation is required. No second invoice was issued.'
                : 'Verified capture on a link that was no longer the current quote. The payment was recorded once.',
            0,
            255,
        );
        $payment->save();

        if (! $duplicate) {
            $this->applyFirstSettlement($payment);
        }

        return $payment->fresh() ?? $payment;
    }

    public function withdrawnCaptureIsEligible(
        Payment $payment,
        ?string $gatewayPaymentId,
        ?string $gatewayLinkId,
        ?int $amountPaise,
        ?string $currency,
    ): bool {
        if (! $payment->isLateCaptureTerminal()) {
            return false;
        }
        if (! $this->isPlausibleGatewayPaymentId($gatewayPaymentId)) {
            return false;
        }
        $storedLinkId = trim((string) $payment->razorpay_link_id);
        $incomingLinkId = trim((string) ($gatewayLinkId ?? ''));
        // Terminal recovery still requires the attempt to have a stored link.
        // When the webhook omits the link entity, amount/currency/payment-id
        // evidence is enough; when a link id is present it must match exactly.
        if ($storedLinkId === '') {
            return false;
        }
        if ($incomingLinkId !== '' && $incomingLinkId !== $storedLinkId) {
            return false;
        }
        if ($amountPaise === null || abs($amountPaise - $payment->totalPaise()) > 1) {
            return false;
        }
        if ($currency === null || strtoupper($currency) !== PricingAmounts::CURRENCY || ! $payment->currencyIsInr()) {
            return false;
        }

        return true;
    }

    /**
     * Whether finance staff may run the verified Razorpay capture check.
     * Active attempts and recent terminal attempts with a stored link qualify.
     */
    public function canStaffReconcileAttempt(Payment $payment): bool
    {
        if ($payment->gateway !== Payment::GATEWAY_RAZORPAY) {
            return false;
        }
        if (trim((string) $payment->razorpay_link_id) === '') {
            return false;
        }
        if ($payment->isPaidOrBetter() || $payment->isRefunded()) {
            return false;
        }

        return $payment->isActiveAttempt() || $this->isEligibleTerminalForCaptureRecovery($payment, true);
    }

    private function markConfirmedWithdrawn(Payment $payment, string $reason): void
    {
        if ($payment->isSettled() || $payment->status === Payment::STATUS_CANCELLED) {
            return;
        }

        $payment->markFailedExpiredOrCancelled(
            Payment::STATUS_CANCELLED,
            'SUPERSEDED',
            mb_substr($reason, 0, 255),
        );

        if ($payment->razorpay_link_url !== null) {
            $payment->razorpay_link_url = null;
            $payment->save();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPaymentLink(string $linkId): ?array
    {
        try {
            $response = $this->http()->get(self::RAZORPAY_LINKS_ENDPOINT.'/'.urlencode($linkId));
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful() || ! is_array($response->json())) {
            return null;
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestLinkCancellation(string $linkId): ?array
    {
        try {
            $response = $this->http()->post(self::RAZORPAY_LINKS_ENDPOINT.'/'.urlencode($linkId).'/cancel');
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($response->json())) {
            return null;
        }

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $link
     */
    private function recordGatewayPaidLink(Payment $payment, array $link): bool
    {
        $linkAmount = isset($link['amount']) ? (int) $link['amount'] : null;
        $linkCurrency = isset($link['currency']) ? (string) $link['currency'] : null;
        if ($linkAmount === null || abs($linkAmount - $payment->totalPaise()) > 1) {
            return false;
        }
        if ($linkCurrency === null || strtoupper($linkCurrency) !== PricingAmounts::CURRENCY) {
            return false;
        }

        $gatewayPaymentId = $this->paymentIdFromLink($link);
        if ($gatewayPaymentId === null) {
            return false;
        }

        $details = $this->fetchGatewayPayment($gatewayPaymentId);
        if ($details === null) {
            return false;
        }
        if ((string) ($details['id'] ?? '') !== $gatewayPaymentId) {
            return false;
        }
        $detailAmount = isset($details['amount']) ? (int) $details['amount'] : null;
        $detailCurrency = isset($details['currency']) ? (string) $details['currency'] : null;
        $detailStatus = strtolower((string) ($details['status'] ?? ''));
        if ($detailAmount === null || abs($detailAmount - $payment->totalPaise()) > 1) {
            return false;
        }
        if ($detailCurrency === null || strtoupper($detailCurrency) !== PricingAmounts::CURRENCY) {
            return false;
        }
        if ($detailStatus !== 'captured') {
            return false;
        }

        $this->recordVerifiedCapture($payment, $gatewayPaymentId, null, now());

        return $payment->fresh()?->isPaidOrBetter() === true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchGatewayPayment(string $gatewayPaymentId): ?array
    {
        try {
            $response = $this->http()->get('https://api.razorpay.com/v1/payments/'.urlencode($gatewayPaymentId));
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful() || ! is_array($response->json())) {
            return null;
        }

        return $response->json();
    }

    /**
     * Confirm a settlement webhook that did not itself prove capture.
     * Returns the captured gateway payment id, or null when the gateway is
     * unavailable or the identifiers, amount, currency, or status do not match.
     * A missing payment id is resolved from the authenticated payment link.
     */
    public function captureIsConfirmedByGateway(Payment $payment, ?string $gatewayPaymentId, ?string $gatewayLinkId): ?string
    {
        if (! $this->config()['enabled']) {
            return null;
        }

        $linkId = trim((string) ($gatewayLinkId !== null && $gatewayLinkId !== '' ? $gatewayLinkId : $payment->razorpay_link_id));
        $storedLinkId = trim((string) $payment->razorpay_link_id);
        if ($linkId === '' || ($storedLinkId !== '' && $storedLinkId !== $linkId)) {
            return null;
        }

        $link = $this->fetchPaymentLink($linkId);
        if ($link === null) {
            return null;
        }

        $linkAmount = isset($link['amount']) ? (int) $link['amount'] : null;
        if ($linkAmount !== null && abs($linkAmount - $payment->totalPaise()) > 1) {
            return null;
        }

        $webhookNamedPayment = $this->isPlausibleGatewayPaymentId($gatewayPaymentId);
        $candidateIds = $webhookNamedPayment
            ? [(string) $gatewayPaymentId]
            : $this->plausiblePaymentIdsOnLink($link);

        foreach ($candidateIds as $candidateId) {
            if (! $this->linkListsPaymentId($link, $candidateId)) {
                return null;
            }

            $details = $this->fetchGatewayPayment($candidateId);
            if ($details === null) {
                return null;
            }
            if ($this->gatewayPaymentDetailsMatchCapture($payment, $candidateId, $details)) {
                return $candidateId;
            }
            if ($webhookNamedPayment) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $link
     * @return list<string>
     */
    private function plausiblePaymentIdsOnLink(array $link): array
    {
        $payments = $link['payments'] ?? null;
        if (! is_array($payments)) {
            return [];
        }

        $ids = [];
        foreach ($payments as $entry) {
            $candidate = null;
            if (is_string($entry)) {
                $candidate = $entry;
            } elseif (is_array($entry) && isset($entry['id'])) {
                $candidate = (string) $entry['id'];
            }
            if ($this->isPlausibleGatewayPaymentId($candidate) && ! in_array($candidate, $ids, true)) {
                $ids[] = $candidate;
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function gatewayPaymentDetailsMatchCapture(Payment $payment, string $gatewayPaymentId, array $details): bool
    {
        if ((string) ($details['id'] ?? '') !== $gatewayPaymentId) {
            return false;
        }

        $amount = isset($details['amount']) ? (int) $details['amount'] : null;
        $currency = isset($details['currency']) ? strtoupper((string) $details['currency']) : null;
        $status = strtolower((string) ($details['status'] ?? ''));

        return $amount !== null
            && abs($amount - $payment->totalPaise()) <= 1
            && $currency === PricingAmounts::CURRENCY
            && $payment->currencyIsInr()
            && $status === 'captured';
    }

    /**
     * @param  array<string, mixed>  $link
     */
    private function linkListsPaymentId(array $link, string $gatewayPaymentId): bool
    {
        $payments = $link['payments'] ?? null;
        if (! is_array($payments)) {
            return false;
        }

        foreach ($payments as $entry) {
            if (is_string($entry) && $entry === $gatewayPaymentId) {
                return true;
            }
            if (is_array($entry) && (string) ($entry['id'] ?? '') === $gatewayPaymentId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $link
     */
    private function paymentIdFromLink(array $link): ?string
    {
        $payments = $link['payments'] ?? null;
        if (! is_array($payments) || $payments === []) {
            return null;
        }

        $first = $payments[0];
        if (is_string($first) && $this->isPlausibleGatewayPaymentId($first)) {
            return $first;
        }
        if (is_array($first) && isset($first['id']) && $this->isPlausibleGatewayPaymentId((string) $first['id'])) {
            return (string) $first['id'];
        }

        return null;
    }

    public function isPlausibleGatewayPaymentId(?string $id): bool
    {
        return is_string($id) && preg_match('/^pay_[A-Za-z0-9_]{3,64}$/', $id) === 1;
    }

    private function anotherSettledPaymentExists(Payment $payment): bool
    {
        if ($payment->application_id === null && $payment->membership_id === null) {
            return false;
        }

        return Payment::query()
            ->where('id', '!=', $payment->id)
            ->where(function ($query) use ($payment): void {
                $matched = false;
                if ($payment->application_id !== null) {
                    $query->where('application_id', $payment->application_id);
                    $matched = true;
                }
                if ($payment->membership_id !== null) {
                    if ($matched) {
                        $query->orWhere('membership_id', $payment->membership_id);
                    } else {
                        $query->where('membership_id', $payment->membership_id);
                    }
                }
            })
            ->whereIn('status', [Payment::STATUS_PAID, Payment::STATUS_CAPTURED, Payment::STATUS_SUCCESS])
            ->exists();
    }

    private function applyFirstSettlement(Payment $payment): void
    {
        if ($payment->application_id !== null) {
            app(ApplicationPaymentStateService::class)->afterSettled($payment);
        }

        if ($payment->item_type === Payment::ITEM_MEMBERSHIP && $payment->membership_id !== null) {
            app(MembershipLifecycleService::class)->applySettledRenewal($payment->fresh() ?? $payment);
        }

        try {
            app(InvoiceService::class)->assignSettlementDocuments($payment);
        } catch (\Throwable $e) {
            Log::warning('Settlement document assignment failed.', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function http(): PendingRequest
    {
        $config = $this->config();
        $this->assertConfigPresent($config);

        return Http::withBasicAuth($config['key_id'], $config['key_secret'])
            ->asJson()
            ->acceptJson()
            ->timeout((int) ($config['timeout_seconds'] ?? 15));
    }

    public function config(): array
    {
        $raw = (array) config('services.razorpay', []);

        return [
            'enabled' => (bool) ($raw['enabled'] ?? false),
            'mode' => (string) ($raw['mode'] ?? 'test'),
            'key_id' => (string) ($raw['key_id'] ?? ''),
            'key_secret' => (string) ($raw['key_secret'] ?? ''),
            'webhook_secret' => (string) ($raw['webhook_secret'] ?? ''),
            'timeout_seconds' => (int) ($raw['timeout_seconds'] ?? 15),
            'require_test_prefix' => (bool) ($raw['require_test_prefix'] ?? true),
        ];
    }

    public function assertConfigPresent(array $config): void
    {
        if (($config['key_id'] ?? '') === '') {
            throw new RuntimeException('Razorpay key_id is not configured.');
        }
        if (($config['key_secret'] ?? '') === '') {
            throw new RuntimeException('Razorpay key_secret is not configured.');
        }

        $mode = strtolower((string) ($config['mode'] ?? 'test'));
        if ($mode === 'live') {
            // S2 — live mode must never run on test keys: charges would be
            // created against a test account while records claim live money.
            if (strncmp((string) $config['key_id'], self::LIVE_KEY_PREFIX, strlen(self::LIVE_KEY_PREFIX)) !== 0) {
                throw new RuntimeException('Razorpay live mode requires key_id starting with "'.self::LIVE_KEY_PREFIX.'".');
            }

            return;
        }

        if (! empty($config['require_test_prefix']) && str_contains($mode, 'test')) {
            if (strncmp((string) $config['key_id'], self::TEST_KEY_PREFIX, strlen(self::TEST_KEY_PREFIX)) !== 0) {
                throw new RuntimeException('Razorpay test mode requires key_id starting with "'.self::TEST_KEY_PREFIX.'".');
            }
        }
    }

    private function buildLinkPayload(Application $application, Payment $payment, array $amounts, int $totalPaise): array
    {
        $callbackUrl = route('payments.razorpay.callback', ['payment_id' => $payment->id], true);
        $cancelUrl = route('applications.payment', ['application' => $application->id], true);

        $customer = [];
        $name = trim((string) ($application->full_name ?? ''));
        if ($name !== '') {
            $customer['name'] = mb_substr($name, 0, 64);
        }
        $email = trim((string) ($application->preferred_contact_email ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $customer['email'] = $email;
        }
        $mobile = trim((string) ($application->preferred_contact_mobile ?? ''));
        if ($mobile !== '') {
            $customer['contact'] = mb_substr(preg_replace('/[^0-9+]/', '', $mobile), 0, 16);
        }

        $payload = [
            'amount' => $totalPaise,
            'currency' => PricingAmounts::CURRENCY,
            'accept_partial' => false,
            'reference_id' => (string) $payment->transaction_reference,
            'description' => mb_substr('Jannayaks '.$amounts['label'].' — '.$name, 0, 255),
            'notify' => [
                'sms' => isset($customer['contact']),
                'email' => isset($customer['email']),
            ],
            'reminder_enable' => true,
            'notes' => [
                'payment_id' => (string) $payment->id,
                'application_id' => (string) $application->id,
                'package_tier' => (string) $application->package_tier,
                'event_type' => 'application_package',
                'item_type' => Payment::ITEM_APPLICATION_PAYMENT,
            ],
            'callback_url' => $callbackUrl,
            'callback_method' => 'get',
            'cancel_url' => $cancelUrl,
            'cancel_method' => 'get',
        ];

        if ($customer !== []) {
            $payload['customer'] = $customer;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $amounts
     * @return array<string, mixed>
     */
    private function buildMembershipRenewalLinkPayload(
        Membership $membership,
        Payment $payment,
        array $amounts,
        int $totalPaise,
    ): array {
        $profile = $membership->profile;
        $callbackUrl = route('payments.razorpay.callback', ['payment_id' => $payment->id], true);
        $cancelUrl = $profile
            ? route('membership.show', $profile, true)
            : route('payments.razorpay.callback', ['payment_id' => $payment->id], true);

        $customer = [];
        $name = trim((string) ($profile?->full_name ?? ''));
        if ($name !== '') {
            $customer['name'] = mb_substr($name, 0, 64);
        }
        $email = trim((string) ($profile?->user?->email ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $customer['email'] = $email;
        }

        $payload = [
            'amount' => $totalPaise,
            'currency' => PricingAmounts::CURRENCY,
            'accept_partial' => false,
            'reference_id' => (string) $payment->transaction_reference,
            'description' => mb_substr('Jannayaks '.$amounts['label'].' — '.$name, 0, 255),
            'notify' => [
                'sms' => false,
                'email' => isset($customer['email']),
            ],
            'reminder_enable' => true,
            'notes' => [
                'payment_id' => (string) $payment->id,
                'membership_id' => (string) $membership->id,
                'profile_id' => (string) ($profile?->id ?? ''),
                'event_type' => 'renewal',
                'item_type' => Payment::ITEM_MEMBERSHIP,
            ],
            'callback_url' => $callbackUrl,
            'callback_method' => 'get',
            'cancel_url' => $cancelUrl,
            'cancel_method' => 'get',
        ];

        if ($customer !== []) {
            $payload['customer'] = $customer;
        }

        return $payload;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Payment>
     */
    public function paymentsDueForCaptureReconciliation(): \Illuminate\Support\Collection
    {
        $max = $this->maxAutomaticChecks();
        $minimumAge = now()->subMinutes($this->minimumAgeMinutes());
        $batch = max(1, (int) config('jannayaks.payment_reconciliation.batch_size', 20));
        $terminalCreatedAfter = now()->subDays($this->terminalRecoveryMaxAgeDays());

        return Payment::query()
            ->where('gateway', Payment::GATEWAY_RAZORPAY)
            ->where(function ($statusQuery) use ($terminalCreatedAfter): void {
                $statusQuery->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_INITIATED])
                    ->orWhere(function ($terminal) use ($terminalCreatedAfter): void {
                        $terminal->whereIn('status', [
                            Payment::STATUS_FAILED,
                            Payment::STATUS_CANCELLED,
                            Payment::STATUS_EXPIRED,
                        ])->where('created_at', '>=', $terminalCreatedAfter);
                    });
            })
            ->whereNotNull('razorpay_link_id')
            ->where('razorpay_link_id', '!=', '')
            ->where('gateway_check_count', '<', $max)
            ->where(function ($query): void {
                $query->whereNull('error_code')
                    ->orWhere('error_code', '!=', self::CODE_RECONCILIATION_MISMATCH);
            })
            ->where(function ($query) use ($minimumAge, $max): void {
                $query->where(function ($first) use ($minimumAge): void {
                    $first->where('gateway_check_count', 0)
                        ->where('created_at', '<=', $minimumAge);
                })->orWhere(function ($later) use ($max): void {
                    $later->where('gateway_check_count', 1)
                        ->where('gateway_checked_at', '<=', now()->subHours(2));
                })->orWhere(function ($later) use ($max): void {
                    $later->where('gateway_check_count', 2)
                        ->where('gateway_checked_at', '<=', now()->subHours(6));
                })->orWhere(function ($later) use ($max): void {
                    $later->where('gateway_check_count', 3)
                        ->where('gateway_checked_at', '<=', now()->subHours(12));
                })->orWhere(function ($later) use ($max): void {
                    $later->where('gateway_check_count', '>=', 4)
                        ->where('gateway_check_count', '<', $max)
                        ->where('gateway_checked_at', '<=', now()->subDay());
                });
            })
            ->orderByRaw('gateway_checked_at ASC NULLS FIRST')
            ->orderBy('id')
            ->limit($batch)
            ->get();
    }

    /**
     * Claim the attempt, read Razorpay without holding a row lock, then apply
     * the result only if the stored attempt is still the one that was claimed.
     */
    public function reconcileOpenAttempt(Payment $payment, bool $staffRequested = false): string
    {
        if (! $this->config()['enabled']) {
            return self::OUTCOME_DISABLED;
        }

        $claim = $this->claimReconciliationAttempt($payment, $staffRequested);
        if ($claim === null) {
            return self::OUTCOME_SKIPPED;
        }

        $evidence = $this->collectReconciliationEvidence($claim);

        return $this->applyReconciliationEvidence($claim, $evidence);
    }

    /**
     * @return array{payment_id: int, link_id: string, amount_paise: int, currency: string, status: string, check_count: int}|null
     */
    private function claimReconciliationAttempt(Payment $payment, bool $staffRequested): ?array
    {
        return DB::transaction(function () use ($payment, $staffRequested): ?array {
            $row = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
            if (! $row instanceof Payment || ! $this->reconciliationClaimIsAllowed($row, $staffRequested)) {
                return null;
            }

            $row->gateway_checked_at = now();
            $row->gateway_check_count = (int) $row->gateway_check_count + 1;
            $row->save();

            return [
                'payment_id' => (int) $row->id,
                'link_id' => trim((string) $row->razorpay_link_id),
                'amount_paise' => $row->totalPaise(),
                'currency' => strtoupper((string) $row->currency),
                'status' => (string) $row->status,
                'check_count' => (int) $row->gateway_check_count,
            ];
        });
    }

    private function reconciliationClaimIsAllowed(Payment $row, bool $staffRequested): bool
    {
        if (trim((string) $row->razorpay_link_id) === '') {
            return false;
        }
        if ($row->gateway !== Payment::GATEWAY_RAZORPAY) {
            return false;
        }
        if ($row->isPaidOrBetter() || $row->isRefunded()) {
            return false;
        }
        if (! $row->isActiveAttempt() && ! $this->isEligibleTerminalForCaptureRecovery($row, $staffRequested)) {
            return false;
        }

        return $staffRequested
            ? $this->staffCheckIsAllowed($row)
            : $this->automaticCheckIsDue($row);
    }

    /**
     * Terminal attempts stay eligible only while a link id remains and the
     * attempt is still inside the bounded recovery window (staff checks may
     * ignore the created-at window so finance can recover a verified capture).
     */
    private function isEligibleTerminalForCaptureRecovery(Payment $payment, bool $staffRequested = false): bool
    {
        if (! $payment->isLateCaptureTerminal()) {
            return false;
        }
        if (trim((string) $payment->razorpay_link_id) === '') {
            return false;
        }
        if ($payment->error_code === self::CODE_RECONCILIATION_MISMATCH) {
            return false;
        }
        if ($staffRequested) {
            return true;
        }
        if ($payment->created_at === null) {
            return false;
        }

        return $payment->created_at->gte(now()->subDays($this->terminalRecoveryMaxAgeDays()));
    }

    private function staffCheckIsAllowed(Payment $payment): bool
    {
        if ($payment->gateway_checked_at === null) {
            return true;
        }

        $seconds = max(0, (int) config('jannayaks.payment_reconciliation.staff_minimum_seconds', 120));

        return $payment->gateway_checked_at->lte(now()->subSeconds($seconds));
    }

    private function automaticCheckIsDue(Payment $payment): bool
    {
        if ((int) $payment->gateway_check_count >= $this->maxAutomaticChecks()) {
            return false;
        }
        if ($payment->error_code === self::CODE_RECONCILIATION_MISMATCH) {
            return false;
        }

        $count = (int) $payment->gateway_check_count;
        if ($count === 0) {
            return $payment->created_at !== null
                && $payment->created_at->lte(now()->subMinutes($this->minimumAgeMinutes()));
        }
        if ($payment->gateway_checked_at === null) {
            return false;
        }

        $dueAt = match ($count) {
            1 => $payment->gateway_checked_at->copy()->addHours(2),
            2 => $payment->gateway_checked_at->copy()->addHours(6),
            3 => $payment->gateway_checked_at->copy()->addHours(12),
            default => $payment->gateway_checked_at->copy()->addDay(),
        };

        return now()->gte($dueAt);
    }

    /**
     * @param  array{payment_id: int, link_id: string, amount_paise: int, currency: string, status: string, check_count: int}  $claim
     * @return array<string, mixed>
     */
    private function collectReconciliationEvidence(array $claim): array
    {
        $link = $this->fetchPaymentLink($claim['link_id']);
        if ($link === null) {
            return ['kind' => 'unavailable', 'hide_url' => false];
        }

        $reportedLinkId = trim((string) ($link['id'] ?? ''));
        if ($reportedLinkId !== '' && $reportedLinkId !== $claim['link_id']) {
            return ['kind' => 'mismatch', 'reason' => 'link_id'];
        }

        $linkAmount = isset($link['amount']) ? (int) $link['amount'] : null;
        if ($linkAmount !== null && abs($linkAmount - $claim['amount_paise']) > 1) {
            return ['kind' => 'mismatch', 'reason' => 'link_amount'];
        }
        $linkCurrency = isset($link['currency']) ? strtoupper((string) $link['currency']) : null;
        if ($linkCurrency !== null && $linkCurrency !== PricingAmounts::CURRENCY) {
            return ['kind' => 'mismatch', 'reason' => 'link_currency'];
        }

        $linkStatus = strtolower((string) ($link['status'] ?? ''));
        $ids = $this->plausiblePaymentIdsOnLink($link);
        $limit = max(1, (int) config('jannayaks.payment_reconciliation.max_payments_per_link', 5));
        $truncated = count($ids) > $limit;
        $inspected = array_slice($ids, 0, $limit);
        $payments = [];
        foreach ($inspected as $gatewayPaymentId) {
            $details = $this->fetchGatewayPayment($gatewayPaymentId);
            if ($details === null) {
                return [
                    'kind' => 'unavailable',
                    'hide_url' => in_array($linkStatus, ['cancelled', 'expired'], true),
                    'link_status' => $linkStatus,
                ];
            }
            $payments[] = $details;
        }

        return [
            'kind' => 'link',
            'link_status' => $linkStatus,
            'payments' => $payments,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  array{payment_id: int, link_id: string, amount_paise: int, currency: string, status: string, check_count: int}  $claim
     * @param  array<string, mixed>  $evidence
     */
    private function applyReconciliationEvidence(array $claim, array $evidence): string
    {
        return DB::transaction(function () use ($claim, $evidence): string {
            $row = Payment::query()->whereKey($claim['payment_id'])->lockForUpdate()->first();
            if (! $row instanceof Payment) {
                return self::OUTCOME_STALE;
            }
            if ($row->isPaidOrBetter()) {
                $this->auditReconciliation($row, $claim['status'], self::OUTCOME_ALREADY_SETTLED);

                return self::OUTCOME_ALREADY_SETTLED;
            }
            $rowStillClaimable = $row->isActiveAttempt()
                || $this->isEligibleTerminalForCaptureRecovery($row, true);
            if (! $this->claimStillMatches($row, $claim) || ! $rowStillClaimable) {
                return self::OUTCOME_STALE;
            }

            $kind = (string) ($evidence['kind'] ?? 'unavailable');
            if ($kind === 'mismatch') {
                $this->flagReconciliation($row, self::CODE_RECONCILIATION_MISMATCH, 'Gateway amount, currency, or link did not match this attempt.');
                $this->hidePaymentLinkUrl($row);
                $this->auditReconciliation($row, $claim['status'], self::OUTCOME_MISMATCH);

                return self::OUTCOME_MISMATCH;
            }

            if ($kind !== 'link') {
                if (($evidence['hide_url'] ?? false) === true) {
                    $this->hidePaymentLinkUrl($row);
                }
                $outcome = $this->flagIfChecksExhausted($row);
                $this->auditReconciliation($row, $claim['status'], $outcome);

                return $outcome;
            }

            $classified = $this->classifyLinkPayments($row, $evidence['payments'] ?? []);
            if ($classified['mismatch']) {
                $this->flagReconciliation($row, self::CODE_RECONCILIATION_MISMATCH, 'Gateway amount, currency, or link did not match this attempt.');
                $this->hidePaymentLinkUrl($row);
                $this->auditReconciliation($row, $claim['status'], self::OUTCOME_MISMATCH);

                return self::OUTCOME_MISMATCH;
            }

            if ($classified['captured'] !== []) {
                return $this->settleReconciledCapture(
                    $row,
                    $claim['status'],
                    $classified['captured'],
                    $classified['unresolved'] !== [] || ($evidence['truncated'] ?? false) === true,
                );
            }

            $linkStatus = (string) ($evidence['link_status'] ?? '');
            $inconclusive = $classified['unresolved'] !== [] || ($evidence['truncated'] ?? false) === true;
            if (in_array($linkStatus, ['cancelled', 'expired'], true) && ! $inconclusive) {
                if ($row->isLateCaptureTerminal()) {
                    $this->hidePaymentLinkUrl($row);
                    $this->auditReconciliation($row, $claim['status'], self::OUTCOME_CLOSED);

                    return self::OUTCOME_CLOSED;
                }
                $target = $linkStatus === 'expired' ? Payment::STATUS_EXPIRED : Payment::STATUS_CANCELLED;
                $row->markFailedExpiredOrCancelled(
                    $target,
                    self::CODE_LINK_CLOSED,
                    'Gateway link is '.$linkStatus.' and no captured or unresolved payment remains.',
                );
                $this->hidePaymentLinkUrl($row);
                $this->auditReconciliation($row, $claim['status'], self::OUTCOME_CLOSED);

                return self::OUTCOME_CLOSED;
            }

            if (in_array($linkStatus, ['cancelled', 'expired'], true)) {
                $this->hidePaymentLinkUrl($row);
            }
            $outcome = $this->flagIfChecksExhausted($row);
            $this->auditReconciliation($row, $claim['status'], $outcome);

            return $outcome;
        });
    }

    /**
     * @param  array{payment_id: int, link_id: string, amount_paise: int, currency: string, status: string, check_count: int}  $claim
     */
    private function claimStillMatches(Payment $row, array $claim): bool
    {
        return trim((string) $row->razorpay_link_id) === $claim['link_id']
            && $row->totalPaise() === $claim['amount_paise']
            && strtoupper((string) $row->currency) === $claim['currency']
            && $row->currencyIsInr();
    }

    /**
     * @param  array<int, mixed>  $payments
     * @return array{captured: list<string>, unresolved: list<string>, mismatch: bool}
     */
    private function classifyLinkPayments(Payment $payment, array $payments): array
    {
        $captured = [];
        $unresolved = [];
        foreach ($payments as $details) {
            if (! is_array($details)) {
                return ['captured' => [], 'unresolved' => [], 'mismatch' => true];
            }
            $gatewayPaymentId = (string) ($details['id'] ?? '');
            $status = strtolower((string) ($details['status'] ?? ''));
            if ($status === 'captured') {
                if (! $this->gatewayPaymentDetailsMatchCapture($payment, $gatewayPaymentId, $details)) {
                    return ['captured' => [], 'unresolved' => [], 'mismatch' => true];
                }
                $captured[] = $gatewayPaymentId;

                continue;
            }
            if (! in_array($status, ['failed', 'refunded'], true)) {
                $unresolved[] = $gatewayPaymentId;
            }
        }

        return ['captured' => $captured, 'unresolved' => $unresolved, 'mismatch' => false];
    }

    /**
     * @param  list<string>  $capturedIds
     */
    private function settleReconciledCapture(Payment $row, string $previousStatus, array $capturedIds, bool $additionalAttention): string
    {
        $gatewayPaymentId = $capturedIds[0];
        if ($this->anotherSettledPaymentExists($row)) {
            $settled = $this->recordVerifiedCapture($row, $gatewayPaymentId, null, now());
            $this->auditReconciliation($settled, $previousStatus, self::OUTCOME_DUPLICATE);

            return $settled->error_code === self::CODE_DUPLICATE_CAPTURE
                ? self::OUTCOME_DUPLICATE
                : self::OUTCOME_STALE;
        }

        $wasTerminal = in_array($previousStatus, [
            Payment::STATUS_FAILED,
            Payment::STATUS_CANCELLED,
            Payment::STATUS_EXPIRED,
        ], true);

        if ($wasTerminal) {
            $row->settleWithdrawnCapture(
                targetStatus: Payment::STATUS_CAPTURED,
                gatewayPaymentId: $gatewayPaymentId,
                gatewayEventId: null,
                paidAt: now(),
            );
        } else {
            $row->markPaidOrCaptured(
                targetStatus: Payment::STATUS_CAPTURED,
                gatewayPaymentId: $gatewayPaymentId,
                gatewayEventId: null,
                eventType: Payment::EVENT_PAYMENT_CAPTURED,
                paidAt: now(),
                capturedAt: now(),
            );
        }
        if (! $row->isPaidOrBetter()) {
            return self::OUTCOME_STALE;
        }

        $this->hidePaymentLinkUrl($row);
        $this->applyFirstSettlement($row);
        if ($additionalAttention || count($capturedIds) > 1) {
            $this->flagReconciliation(
                $row,
                self::CODE_ADDITIONAL_CAPTURE,
                'Verified capture was settled once. Another gateway payment on this link needs staff reconciliation.',
            );
        } elseif ($wasTerminal) {
            $row->error_code = self::CODE_SETTLED_ON_WITHDRAWN_LINK;
            $row->error_message = mb_substr(
                'Verified capture on a link that was no longer the current quote. The payment was recorded once.',
                0,
                255,
            );
            $row->save();
        } elseif (in_array($row->error_code, [self::CODE_RECONCILIATION_MISMATCH, self::CODE_RECONCILIATION_UNRESOLVED], true)) {
            $row->error_code = null;
            $row->error_message = null;
            $row->save();
        }
        $this->auditReconciliation($row, $previousStatus, self::OUTCOME_SETTLED);

        return self::OUTCOME_SETTLED;
    }

    private function flagIfChecksExhausted(Payment $row): string
    {
        if ((int) $row->gateway_check_count < $this->maxAutomaticChecks()) {
            return self::OUTCOME_PENDING;
        }

        $this->flagReconciliation(
            $row,
            self::CODE_RECONCILIATION_UNRESOLVED,
            'Automatic Razorpay checks ended without a confirmed capture. Staff review is required.',
        );

        return self::OUTCOME_UNRESOLVED;
    }

    private function flagReconciliation(Payment $payment, string $code, string $message): void
    {
        $payment->error_code = $code;
        $payment->error_message = mb_substr($message, 0, 255);
        $payment->save();
    }

    private function hidePaymentLinkUrl(Payment $payment): void
    {
        if ($payment->razorpay_link_url === null) {
            return;
        }

        $payment->razorpay_link_url = null;
        $payment->save();
    }

    private function auditReconciliation(Payment $payment, string $previousStatus, string $outcome): void
    {
        if (! in_array($outcome, [
            self::OUTCOME_SETTLED,
            self::OUTCOME_ALREADY_SETTLED,
            self::OUTCOME_DUPLICATE,
            self::OUTCOME_CLOSED,
            self::OUTCOME_MISMATCH,
            self::OUTCOME_UNRESOLVED,
        ], true)) {
            return;
        }

        try {
            app(StaffAuditLogger::class)->log(
                action: 'payment.capture_reconciliation',
                subject: $payment,
                before: ['status' => $previousStatus],
                after: [
                    'outcome' => $outcome,
                    'status' => $payment->status,
                    'error_code' => $payment->error_code,
                    'gateway_payment_id' => $payment->gateway_payment_id,
                    'payment_id' => $payment->id,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('Payment reconciliation audit failed.', [
                'payment_id' => $payment->id,
                'outcome' => $outcome,
            ]);
        }
    }

    private function maxAutomaticChecks(): int
    {
        return max(1, (int) config('jannayaks.payment_reconciliation.max_checks', 12));
    }

    private function minimumAgeMinutes(): int
    {
        return max(0, (int) config('jannayaks.payment_reconciliation.minimum_age_minutes', 30));
    }

    private function terminalRecoveryMaxAgeDays(): int
    {
        return max(1, (int) config('jannayaks.payment_reconciliation.terminal_recovery_max_age_days', 14));
    }
}
