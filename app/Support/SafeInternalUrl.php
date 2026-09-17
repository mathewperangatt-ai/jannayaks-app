<?php

namespace App\Support;

class SafeInternalUrl
{
    /**
     * Return a same-origin path suitable for redirect()->setIntendedUrl(), or null.
     */
    public static function intended(?string $candidate): ?string
    {
        if (! is_string($candidate)) {
            return null;
        }

        $candidate = trim($candidate);

        if ($candidate === '') {
            return null;
        }

        if (str_starts_with($candidate, '/') && ! str_starts_with($candidate, '//') && ! str_contains($candidate, '\\')) {
            if (str_contains($candidate, '://')) {
                return null;
            }

            return $candidate;
        }

        $parts = parse_url($candidate);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($appHost) || $appHost === '') {
            return null;
        }

        if (strcasecmp((string) $parts['host'], $appHost) !== 0) {
            return null;
        }

        $path = $parts['path'] ?? '/';

        if ($path === '') {
            $path = '/';
        }

        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?'.$parts['query'];
        }

        return $path;
    }
}
