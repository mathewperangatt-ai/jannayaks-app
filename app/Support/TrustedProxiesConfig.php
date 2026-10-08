<?php

namespace App\Support;

class TrustedProxiesConfig
{
    /**
     * Cloudflare published IP ranges (https://www.cloudflare.com/ips/) — the
     * edge that forwards public traffic to the origin. Requests that reach the
     * application through Cloudflare carry the client IP in the
     * X-Forwarded-For chain only if this edge is trusted.
     */
    public const CLOUDFLARE_RANGES = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    /**
     * RFC 1918 / CGNAT / loopback / link-local / unique-local — the private hop
     * between the platform ingress (Railway) and the application container. The
     * ingress appends the previous hop to X-Forwarded-For; trusting only the
     * private ranges lets Laravel walk the chain past it to the real client.
     */
    public const PRIVATE_RANGES = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '100.64.0.0/10',
        '127.0.0.1/32',
        '::1/128',
        'fc00::/7',
    ];

    /**
     * Trusted-proxy configuration for the HTTP kernel.
     *
     * Default (no TRUSTED_PROXIES): Cloudflare published ranges + private
     * platform ranges — tight enough that arbitrary internet hosts cannot
     * spoof X-Forwarded-For, while still resolving the real client IP for the
     * Cloudflare -> Railway -> container path of this deployment.
     *
     * TRUSTED_PROXIES accepts "*" (trust all — must be set EXPLICITLY), or a
     * comma-separated list of IPs / CIDR ranges which replaces the default.
     */
    public static function fromEnv(): string|array
    {
        return self::parse((string) env('TRUSTED_PROXIES', ''));
    }

    /**
     * @return string|array<int, string> "*" or a list of IP/CIDR strings
     */
    public static function parse(string $raw): string|array
    {
        $raw = trim($raw);

        if ($raw === '*') {
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
            return array_values(array_merge(self::CLOUDFLARE_RANGES, self::PRIVATE_RANGES));
        }

        return array_values(array_unique($entries));
    }
}
