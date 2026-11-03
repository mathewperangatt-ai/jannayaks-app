<?php

namespace App\Hosting;

use Illuminate\Http\Request;

/**
 * HMAC-SHA256 request authentication for the IconsHerald Hosting integration.
 *
 * Signed string (UTF-8):
 *   {METHOD}\n{PATH_WITH_QUERY}\n{TIMESTAMP}\n{RAW_BODY}
 *
 * PATH_WITH_QUERY is the request pathInfo plus the raw query string bytes
 * exactly as transmitted (e.g. /internal/hosting/sitemap-profiles?page=2&per_page=50).
 * Verification deliberately uses the raw QUERY_STRING server variable — not
 * Request::getQueryString(), which re-sorts and re-encodes parameters — so a
 * reordered query signed in its transmitted order validates, and any query
 * rewritten by an intermediary fails closed.
 */
class HostingIntegrationAuthenticator
{
    public const TIMESTAMP_HEADER = 'X-Jannayaks-Hosting-Timestamp';

    public const SIGNATURE_HEADER = 'X-Jannayaks-Hosting-Signature';

    public function isConfigured(): bool
    {
        return $this->sharedSecret() !== '';
    }

    public function sharedSecret(): string
    {
        return trim((string) config('hosting_integration.shared_secret', ''));
    }

    public function timestampToleranceSeconds(): int
    {
        $seconds = (int) config('hosting_integration.timestamp_tolerance_seconds', 300);

        return max(30, $seconds);
    }

    /**
     * Build the canonical string that both peers must HMAC.
     */
    public function stringToSign(string $method, string $pathWithQuery, string $timestamp, string $rawBody): string
    {
        return strtoupper($method)."\n".$pathWithQuery."\n".$timestamp."\n".$rawBody;
    }

    public function pathWithQuery(Request $request): string
    {
        $path = $request->getPathInfo() ?: '/';
        // Raw bytes exactly as received — never Request::getQueryString(),
        // which normalizes (re-sorts and re-encodes) the parameters.
        $query = $request->server('QUERY_STRING');

        return (is_string($query) && $query !== '') ? $path.'?'.$query : $path;
    }

    public function computeSignature(string $stringToSign, string $secret): string
    {
        return hash_hmac('sha256', $stringToSign, $secret);
    }

    /**
     * @return null|string Null when valid; otherwise a short machine reason for logs (never the secret).
     */
    public function validationFailureReason(Request $request): ?string
    {
        $secret = $this->sharedSecret();
        if ($secret === '') {
            return 'secret_missing';
        }

        $timestamp = trim((string) $request->header(self::TIMESTAMP_HEADER, ''));
        $signature = trim((string) $request->header(self::SIGNATURE_HEADER, ''));

        if ($timestamp === '' || $signature === '') {
            return 'headers_missing';
        }

        if (! ctype_digit($timestamp)) {
            return 'timestamp_invalid';
        }

        $age = abs(time() - (int) $timestamp);
        if ($age > $this->timestampToleranceSeconds()) {
            return 'timestamp_expired';
        }

        $stringToSign = $this->stringToSign(
            $request->method(),
            $this->pathWithQuery($request),
            $timestamp,
            (string) $request->getContent(),
        );

        $expected = $this->computeSignature($stringToSign, $secret);

        if (! hash_equals($expected, $signature)) {
            return 'signature_mismatch';
        }

        return null;
    }
}
