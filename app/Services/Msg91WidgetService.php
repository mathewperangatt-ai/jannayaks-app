<?php

namespace App\Services;

use App\Models\User;
use App\Support\IndiaMobile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * MSG91 OTP Widget authentication (Jannayaks-specific integration).
 *
 * Flow: the client-side widget sends + verifies the OTP and returns a JWT
 * access token. This service verifies that token server-side with MSG91
 * (POST /api/v5/widget/verifyAccessToken, AuthKey from server-only env) and
 * resolves the VERIFIED mobile number returned by MSG91 — never a
 * client-supplied number — to the Jannayaks user model.
 *
 * FAIL-CLOSED: missing AuthKey, HTTP failure/timeout, non-2xx status,
 * missing success indication, absent/unmappable mobile number, or any
 * unexpected response shape all reject authentication. Logs carry reason
 * codes and HTTP status only — never the AuthKey, access token, OTP, mobile
 * number, or raw response body.
 */
class Msg91WidgetService
{
    public function __construct()
    {
    }

    /**
     * Verify a widget access token and return the normalized verified mobile.
     *
     * @throws InvalidArgumentException on every failure path (fail-closed)
     */
    public function verifyAccessToken(string $accessToken): string
    {
        $authKey = (string) config('jannayaks.otp.msg91.auth_key', '');
        if ($authKey === '') {
            Log::warning('msg91.widget.login_rejected', ['reason' => 'auth_key_not_configured']);

            throw new InvalidArgumentException('Mobile OTP sign-in is not configured. Please try again later.');
        }

        $accessToken = trim($accessToken);
        if ($accessToken === '' || strlen($accessToken) > 4096 || str_contains($accessToken, "\n")) {
            Log::warning('msg91.widget.login_rejected', ['reason' => 'invalid_access_token_format']);

            throw new InvalidArgumentException('Mobile OTP verification could not be completed. Please try again.');
        }

        try {
            $response = Http::timeout((int) config('jannayaks.otp.msg91.timeout_seconds', 15))
                ->acceptJson()
                ->post((string) config('jannayaks.otp.msg91.verify_url'), [
                    'authkey' => $authKey,
                    'access-token' => $accessToken,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('msg91.widget.login_rejected', ['reason' => 'provider_unreachable']);

            throw new InvalidArgumentException('Mobile OTP verification is temporarily unavailable. Please try again.');
        } catch (Throwable $e) {
            Log::warning('msg91.widget.login_rejected', ['reason' => 'provider_request_error']);

            throw new InvalidArgumentException('Mobile OTP verification is temporarily unavailable. Please try again.');
        }

        if (! $response->successful()) {
            Log::warning('msg91.widget.login_rejected', ['reason' => 'provider_rejected_token', 'http_status' => $response->status()]);

            throw new InvalidArgumentException('Mobile OTP verification failed. Please try again.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            Log::warning('msg91.widget.login_rejected', ['reason' => 'unexpected_response_shape']);

            throw new InvalidArgumentException('Mobile OTP verification failed. Please try again.');
        }

        // Success MUST be explicit; the absence of an error is not success.
        $type = (string) ($payload['type'] ?? '');
        $message = strtolower((string) ($payload['message'] ?? ''));
        $explicitSuccess = $type === 'success'
            || str_contains($message, 'success')
            || str_contains($message, 'verified');
        if (! $explicitSuccess) {
            Log::warning('msg91.widget.login_rejected', ['reason' => 'no_success_indication']);

            throw new InvalidArgumentException('Mobile OTP verification failed. Please try again.');
        }

        $mobile = $this->extractVerifiedMobile($payload);
        if ($mobile === null) {
            Log::warning('msg91.widget.login_rejected', ['reason' => 'verified_mobile_missing']);

            throw new InvalidArgumentException('Mobile OTP verification failed. Please try again.');
        }

        return $mobile;
    }

    /**
     * Map the MSG91-verified mobile number to the Jannayaks user model.
     * Preserves the long-standing OTP design: existing account by mobile is
     * reused; otherwise a minimal verified member account is created (same
     * semantics as the previous local-OTP implementation).
     */
    public function resolveUserForVerifiedMobile(string $normalizedMobile): User
    {
        $existing = User::query()->where('mobile', $normalizedMobile)->first();
        if ($existing instanceof User) {
            if ($existing->mobile_verified_at === null) {
                $existing->forceFill(['mobile_verified_at' => now()])->save();
            }

            return $existing;
        }

        return User::query()->create([
            'name' => 'Jannayaks Member',
            'mobile' => $normalizedMobile,
            'mobile_verified_at' => now(),
            'role' => User::ROLE_MEMBER,
            'account_status' => 'active',
            'password' => null,
        ]);
    }

    public function widgetConfigured(): bool
    {
        return filled(config('jannayaks.otp.msg91.widget_token'))
            && filled(config('jannayaks.otp.msg91.widget_id'));
    }

    /**
     * Defensive extraction of the verified identifier from the MSG91
     * response. Accepts the documented field names; normalizes strictly to
     * the Indian mobile format and rejects everything else.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractVerifiedMobile(array $payload): ?string
    {
        $candidates = [
            $payload['mobile'] ?? null,
            $payload['phone'] ?? null,
            $payload['identifier'] ?? null,
            is_array($payload['data'] ?? null) ? ($payload['data']['mobile'] ?? null) : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                try {
                    return IndiaMobile::normalize($candidate);
                } catch (InvalidArgumentException) {
                    continue;
                }
            }
        }

        return null;
    }
}
