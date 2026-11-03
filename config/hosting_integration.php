<?php

/**
 * IconsHerald Jannayaks Hosting — server-to-server read-only integration.
 *
 * Provisioning and rotation
 * -------------------------
 * 1. Generate a long random secret (e.g. 32+ bytes from a CSPRNG) offline.
 * 2. Set JANNAYAKS_HOSTING_INTEGRATION_SECRET on Jannayaks only via the host
 *    environment / secrets manager. Never commit the value.
 * 3. Provide the same secret to the IconsHerald Hosting module through its
 *    own secrets store. Do not share database or R2 credentials.
 * 4. To rotate: generate a new secret, deploy it to both sides during a short
 *    maintenance window (or dual-accept briefly if a future dual-secret
 *    window is added), then retire the old value. This config accepts a
 *    single active secret only.
 * 5. If the secret is unset or empty, all hosting integration routes fail
 *    closed (no profile data is returned).
 *
 * Photo URLs returned by this integration are the existing Jannayaks public
 * photo routes. Those responses use Cache-Control: public, max-age=300, so
 * after withdrawal a CDN/browser may retain bytes for up to five minutes.
 * The photo controller is intentionally unchanged by this integration.
 */

return [
    'shared_secret' => (string) env('JANNAYAKS_HOSTING_INTEGRATION_SECRET', ''),

    /** Maximum age of X-Jannayaks-Hosting-Timestamp in seconds. */
    'timestamp_tolerance_seconds' => (int) env('JANNAYAKS_HOSTING_INTEGRATION_TIMESTAMP_TOLERANCE', 300),

    'rate_limit_per_minute' => (int) env('JANNAYAKS_HOSTING_INTEGRATION_RATE_LIMIT', 60),

    'sitemap' => [
        'default_per_page' => (int) env('JANNAYAKS_HOSTING_INTEGRATION_SITEMAP_PER_PAGE', 100),
        'max_per_page' => (int) env('JANNAYAKS_HOSTING_INTEGRATION_SITEMAP_MAX_PER_PAGE', 200),
    ],
];
