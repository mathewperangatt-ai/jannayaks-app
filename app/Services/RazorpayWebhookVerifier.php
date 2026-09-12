<?php

namespace App\Services;

use RuntimeException;

class RazorpayWebhookVerifier
{
    public const SIGNATURE_HEADER = 'X-Razorpay-Signature';

    public function verify(string $rawPayload, string $signatureHeader, string $webhookSecret): bool
    {
        if ($webhookSecret === '') {
            throw new RuntimeException('Razorpay webhook secret is not configured.');
        }
        if ($signatureHeader === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $webhookSecret);

        return hash_equals($expected, $signatureHeader);
    }

    public function computeSignature(string $rawPayload, string $webhookSecret): string
    {
        if ($webhookSecret === '') {
            throw new RuntimeException('Razorpay webhook secret is not configured.');
        }

        return hash_hmac('sha256', $rawPayload, $webhookSecret);
    }
}
