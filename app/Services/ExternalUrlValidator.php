<?php

namespace App\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;

class ExternalUrlValidator
{
    /**
     * Validate and normalize an external HTTPS URL without fetching remote content.
     *
     * @throws InvalidArgumentException
     */
    public function validateHttpsUrl(string $raw): string
    {
        $trimmed = trim($raw);

        if ($trimmed === '' || Str::length($trimmed) > 2048) {
            throw new InvalidArgumentException('A valid HTTPS URL is required.');
        }

        if (preg_match('/\s/', $trimmed) === 1) {
            throw new InvalidArgumentException('URLs must not contain whitespace.');
        }

        $lower = Str::lower($trimmed);
        foreach (['javascript:', 'data:', 'file:', 'vbscript:', 'about:'] as $scheme) {
            if (Str::startsWith($lower, $scheme)) {
                throw new InvalidArgumentException('This URL scheme is not allowed.');
            }
        }

        if (Str::contains($trimmed, ['<', '>', '"', "'"])) {
            throw new InvalidArgumentException('URLs must not contain HTML markup.');
        }

        if (! filter_var($trimmed, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('The URL format is invalid.');
        }

        $parts = parse_url($trimmed);
        if (! is_array($parts)) {
            throw new InvalidArgumentException('The URL could not be parsed.');
        }

        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== 'https') {
            throw new InvalidArgumentException('Only HTTPS URLs are allowed.');
        }

        $host = (string) ($parts['host'] ?? '');
        if ($host === '' || Str::contains($host, [' ', '/'])) {
            throw new InvalidArgumentException('The URL host is invalid.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('URLs must not include credentials.');
        }

        // Reject obvious loopback/private hosts without performing outbound requests (SSRF hygiene).
        $hostLower = Str::lower($host);
        if (in_array($hostLower, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            throw new InvalidArgumentException('This host is not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('This host is not allowed.');
            }
        }

        return $trimmed;
    }
}
