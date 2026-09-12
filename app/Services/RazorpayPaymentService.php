<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Payment;
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

        $existing = Payment::query()
            ->where('application_id', $application->id)
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_INITIATED])
            ->first();

        if ($existing instanceof Payment) {
            if ($existing->razorpay_link_id !== null && $existing->razorpay_link_url !== null) {
                return $existing;
            }
        }

        $amounts = PricingAmounts::forTier($application->package_tier);
        PricingAmounts::assertInr($amounts['currency']);

        $totalPaise = (int) $amounts['amount_incl_paise'];
        if ($totalPaise <= 0) {
            throw new RuntimeException('Payment amount must be positive.');
        }

        return DB::transaction(function () use ($application, $amounts, $totalPaise, $existing) {
            if ($existing instanceof Payment) {
                $payment = $existing;
            } else {
                $txnRef = 'JNK-PAY-'.$application->id.'-'.Str::upper(Str::random(10));
                $payment = Payment::query()->create([
                    'application_id'   => $application->id,
                    'transaction_reference' => $txnRef,
                    'gateway'          => Payment::GATEWAY_RAZORPAY,
                    'item_type'        => Payment::ITEM_APPLICATION_PAYMENT,
                    'amount'           => PricingAmounts::paiseToDecimalString($totalPaise),
                    'currency'         => PricingAmounts::CURRENCY,
                    'status'           => Payment::STATUS_PENDING,
                    'event_type'       => 'application_package',
                    'base_amount'      => PricingAmounts::paiseToDecimalString((int) $amounts['base_paise']),
                    'taxable_amount'   => PricingAmounts::paiseToDecimalString((int) $amounts['base_paise']),
                    'gst_rate_percent' => $amounts['gst_rate_percent'],
                    'cgst_amount'      => $amounts['cgst_paise'] !== null ? PricingAmounts::paiseToDecimalString((int) $amounts['cgst_paise']) : null,
                    'sgst_amount'      => $amounts['sgst_paise'] !== null ? PricingAmounts::paiseToDecimalString((int) $amounts['sgst_paise']) : null,
                    'igst_amount'      => $amounts['igst_paise'] !== null ? PricingAmounts::paiseToDecimalString((int) $amounts['igst_paise']) : null,
                ]);
            }

            $config = $this->config();
            if (! $config['enabled']) {
                $payment->markInitiated(Payment::GATEWAY_RAZORPAY);

                return $payment;
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

    public function cancelLink(Payment $payment): void
    {
        if ($payment->razorpay_link_id === null || $payment->razorpay_link_id === '') {
            return;
        }
        if ($payment->isSettled()) {
            return;
        }

        $config = $this->config();
        if (! $config['enabled']) {
            $payment->markFailedExpiredOrCancelled(Payment::STATUS_CANCELLED, null, 'Cancelled locally.');

            return;
        }

        $url = self::RAZORPAY_LINKS_ENDPOINT.'/'.urlencode($payment->razorpay_link_id).'/cancel';
        $response = $this->http()->post($url);
        if ($response->status() === 400) {
            $body = $response->json();
            $desc = is_array($body) && isset($body['error']['description']) ? (string) $body['error']['description'] : '';
            if (stripos($desc, 'expired') !== false || stripos($desc, 'cancelled') !== false) {
                $payment->markFailedExpiredOrCancelled(Payment::STATUS_CANCELLED);

                return;
            }
        }
        if (! $response->successful() && $response->status() !== 400) {
            throw new RuntimeException('Failed to cancel Razorpay payment link (HTTP '.$response->status().').');
        }
        $payment->markFailedExpiredOrCancelled(Payment::STATUS_CANCELLED);
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
            'enabled'         => (bool) ($raw['enabled'] ?? false),
            'mode'            => (string) ($raw['mode'] ?? 'test'),
            'key_id'          => (string) ($raw['key_id'] ?? ''),
            'key_secret'      => (string) ($raw['key_secret'] ?? ''),
            'webhook_secret'  => (string) ($raw['webhook_secret'] ?? ''),
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
            'amount'           => $totalPaise,
            'currency'         => PricingAmounts::CURRENCY,
            'accept_partial'   => false,
            'reference_id'     => (string) $payment->transaction_reference,
            'description'      => mb_substr('Jannayaks '.$amounts['label'].' — '.$name, 0, 255),
            'notify'           => [
                'sms'    => isset($customer['contact']),
                'email'  => isset($customer['email']),
            ],
            'reminder_enable'  => true,
            'notes'            => [
                'payment_id'    => (string) $payment->id,
                'application_id'=> (string) $application->id,
                'package_tier'  => (string) $application->package_tier,
                'event_type'    => 'application_package',
                'item_type'     => Payment::ITEM_APPLICATION_PAYMENT,
            ],
            'callback_url'     => $callbackUrl,
            'callback_method'  => 'get',
            'cancel_url'       => $cancelUrl,
            'cancel_method'    => 'get',
        ];

        if ($customer !== []) {
            $payload['customer'] = $customer;
        }

        return $payload;
    }
}
