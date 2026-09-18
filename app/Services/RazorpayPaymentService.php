<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\User;
use App\Support\PricingAmounts;
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

    public function createApplicationPaymentLink(Application $application): Payment
    {
        if (! in_array($application->package_tier, ['emerging', 'accomplished', 'distinguished'], true)) {
            throw new InvalidArgumentException('Invalid application package tier.');
        }

        $includeAddon = (bool) $application->distinguished_interview_addon
            && $application->package_tier === 'distinguished';

        $amounts = PricingAmounts::forApplicationPackage($application->package_tier, $includeAddon);
        PricingAmounts::assertInr($amounts['currency']);

        $totalPaise = (int) $amounts['amount_incl_paise'];
        if ($totalPaise <= 0) {
            throw new RuntimeException('Payment amount must be positive.');
        }

        return DB::transaction(function () use ($application, $amounts, $totalPaise, $includeAddon) {
            $actives = Payment::query()
                ->where('application_id', $application->id)
                ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_INITIATED])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $reusable = null;
            foreach ($actives as $active) {
                if ($active->totalPaise() === $totalPaise && $reusable === null) {
                    $reusable = $active;

                    continue;
                }

                $this->supersedeAttempt($active, 'Superseded by a newer payment attempt with a different amount or configuration.');
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
                    'event_type' => $includeAddon ? 'distinguished_interview_addon' : 'application_package',
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
                    'event_type' => $includeAddon ? 'distinguished_interview_addon' : 'application_package',
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

            $payload = $this->buildLinkPayload($application, $payment, $amounts, $totalPaise, $includeAddon);
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

        $amounts = PricingAmounts::forAnnualMembership();
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
            foreach ($actives as $active) {
                if ($active->totalPaise() === $totalPaise && $reusable === null) {
                    $reusable = $active;

                    continue;
                }

                $this->supersedeAttempt($active, 'Superseded by a newer membership renewal attempt.');
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
     * Mark a payment attempt superseded/cancelled in the database.
     * Gateway link cancellation is best-effort and must not gate DB supersession.
     */
    public function supersedeAttempt(Payment $payment, string $reason = 'Superseded by a newer payment attempt.'): void
    {
        if ($payment->isSettled()) {
            return;
        }

        if ($payment->status === Payment::STATUS_CANCELLED) {
            return;
        }

        $this->cancelGatewayLinkBestEffort($payment);

        $payment->markFailedExpiredOrCancelled(
            Payment::STATUS_CANCELLED,
            'SUPERSEDED',
            mb_substr($reason, 0, 255),
        );

        // Keep razorpay_link_id for audit / late-webhook association; drop the usable URL.
        if ($payment->razorpay_link_url !== null) {
            $payment->razorpay_link_url = null;
            $payment->save();
        }
    }

    public function cancelLink(Payment $payment): void
    {
        $this->supersedeAttempt($payment, 'Payment attempt cancelled.');
    }

    private function cancelGatewayLinkBestEffort(Payment $payment): void
    {
        if ($payment->razorpay_link_id === null || $payment->razorpay_link_id === '') {
            return;
        }

        $config = $this->config();
        if (! $config['enabled']) {
            return;
        }

        try {
            $url = self::RAZORPAY_LINKS_ENDPOINT.'/'.urlencode($payment->razorpay_link_id).'/cancel';
            $this->http()->post($url);
        } catch (\Throwable) {
            // Database supersession remains authoritative even if gateway cancel fails.
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
        if (! empty($config['require_test_prefix']) && stripos((string) $config['mode'], 'test') !== false) {
            if (strncmp((string) $config['key_id'], self::TEST_KEY_PREFIX, strlen(self::TEST_KEY_PREFIX)) !== 0) {
                throw new RuntimeException('Razorpay test mode requires key_id starting with "'.self::TEST_KEY_PREFIX.'".');
            }
        }
    }

    private function buildLinkPayload(Application $application, Payment $payment, array $amounts, int $totalPaise, bool $includeAddon = false): array
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
                'event_type' => $includeAddon ? 'distinguished_interview_addon' : 'application_package',
                'item_type' => Payment::ITEM_APPLICATION_PAYMENT,
                'distinguished_interview_addon' => $includeAddon ? '1' : '0',
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
}
