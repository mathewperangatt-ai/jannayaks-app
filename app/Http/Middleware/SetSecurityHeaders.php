<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global security response headers.
 *
 * - CSP is ENFORCED by default. The member policy covers everything the
 *   public/member pages actually load (self-hosted assets, Google Fonts, the
 *   homepage Google Translate widget, pervasive inline scripts/styles via
 *   'unsafe-inline'). The Filament admin panel gets a dedicated policy that
 *   additionally allows 'unsafe-eval' because Alpine evaluates expressions
 *   through the Function constructor. Set JANNAYAKS_CSP_ENFORCE=false to fall
 *   back to Report-Only while investigating a violation — see
 *   docs/security-headers-csp.md.
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

        $csp = $this->policyFor($request);
        if ($csp !== '') {
            if ((bool) config('jannayaks.security.headers.csp_enforce', true)) {
                $response->headers->set('Content-Security-Policy', $csp);
            } else {
                $response->headers->set('Content-Security-Policy-Report-Only', $csp);
            }
        }

        return $response;
    }

    /**
     * Admin pages use the Filament policy (adds 'unsafe-eval' for Alpine);
     * everything else uses the stricter member policy.
     */
    private function policyFor(Request $request): string
    {
        $key = ($request->is('admin') || $request->is('admin/*'))
            ? 'jannayaks.security.headers.csp_admin'
            : 'jannayaks.security.headers.csp';

        return (string) config($key, '');
    }
}
