<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile server-side verification for the Online Interview
 * submission. Enabled only when both the site key and the secret are
 * configured; verification then uses Cloudflare's Siteverify endpoint
 * (mandatory server-side check — client-side state is never trusted).
 *
 * Tokens are single-use: Cloudflare rejects reused and expired tokens via
 * the invalid / timeout-or-duplicate error codes, both of which map to a
 * failed verification here.
 */
class TurnstileService
{
    public const SITEVERIFY_ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** The integration is active only with both halves configured. */
    public function enabled(): bool
    {
        return trim((string) config('services.turnstile.sitekey')) !== ''
            && trim((string) config('services.turnstile.secret')) !== '';
    }

    /** The site key for the client-side widget (never the secret). */
    public function sitekey(): string
    {
        return (string) config('services.turnstile.sitekey', '');
    }

    /**
     * Verify a Turnstile token server-side. Returns true only when
     * Cloudflare confirms success; missing/blank tokens always fail.
     *
     * @param  string|null  $token  The cf-turnstile-response form field.
     * @param  string|null  $ip  The applicant's IP (optional per Cloudflare docs).
     */
    public function verify(?string $token, ?string $ip = null): bool
    {
        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        $secret = trim((string) config('services.turnstile.secret'));
        if ($secret === '') {
            // Fail closed: an enabled-but-unconfigured secret must never pass.
            Log::warning('turnstile.verify.missing_secret');

            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('services.turnstile.timeout_seconds', 10))
                ->post(self::SITEVERIFY_ENDPOINT, array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (\Throwable $e) {
            Log::warning('turnstile.verify.request_failed', ['error' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('turnstile.verify.http_error', ['status' => $response->status()]);

            return false;
        }

        $result = $response->json();

        return is_array($result)
            && ($result['success'] ?? false) === true;
    }
}
