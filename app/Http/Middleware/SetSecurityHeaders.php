<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global security response headers.
 *
 * - CSP ships in Report-Only mode by default because the codebase currently
 *   relies on inline <script> blocks (home, tier-select, upload, interview,
 *   profile-url), the Google Translate widget (homepage), and Filament's
 *   dynamically injected scripts/styles in the admin panel. Enforcing without
 *   nonce/hash refactoring would break legitimate functionality. Flip
 *   JANNAYAKS_CSP_ENFORCE=true only after production violation reports are
 *   clean — see docs/security-headers-csp.md.
 * - X-Powered-By is removed at the application layer because expose_php is a
 *   PHP_INI_SYSTEM setting that cannot be changed at runtime.
 */
class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        header_remove('X-Powered-By');

        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), usb=(), magnetometer=(), accelerometer=(), gyroscope=(), interest-cohort=()'
        );

        $csp = (string) config('jannayaks.security.headers.csp', '');
        if ($csp !== '') {
            if ((bool) config('jannayaks.security.headers.csp_enforce', false)) {
                $response->headers->set('Content-Security-Policy', $csp);
            } else {
                $response->headers->set('Content-Security-Policy-Report-Only', $csp);
            }
        }

        return $response;
    }
}
