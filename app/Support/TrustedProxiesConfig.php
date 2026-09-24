<?php

namespace App\Support;

class TrustedProxiesConfig
{
    /**
     * Trusted-proxy configuration for the HTTP kernel.
     *
     * TRUSTED_PROXIES accepts "*" (trust all), or a comma-separated list of
     * IPs / CIDR ranges (e.g. Cloudflare + Railway edge ranges). Blank values
     * fall back to "*" so existing deployments keep their current behavior
     * until the variable is explicitly set.
     */
    public static function fromEnv(): string|array
    {
        return self::parse((string) env('TRUSTED_PROXIES', '*'));
    }

    /**
     * @return string|array<int, string> "*" or a list of IP/CIDR strings
     */
    public static function parse(string $raw): string|array
    {
        $raw = trim($raw);

        if ($raw === '' || $raw === '*') {
            return '*';
        }

        $entries = [];
        foreach (explode(',', $raw) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            // A stray "*" anywhere means trust-all wins; safer to surface intent directly.
            if ($candidate === '*') {
                return '*';
            }
            $entries[] = $candidate;
        }

        if ($entries === []) {
            return '*';
        }

        return array_values(array_unique($entries));
    }
}
