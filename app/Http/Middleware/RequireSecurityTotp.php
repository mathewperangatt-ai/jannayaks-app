<?php

namespace App\Http\Middleware;

use App\Security\SecurityTotpService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel gate for TOTP two-factor authentication (Wave 3).
 *
 * security_admin accounts MUST enroll before panel access and MUST pass a
 * fresh TOTP/recovery-code challenge at every login. Other staff roles are
 * not forced (optional enrollment) but pass straight through.
 *
 * Registered in the Filament panel's authMiddleware chain, after
 * Authenticate — it can never be bypassed by an ordinary profile-editing
 * workflow because it runs on every panel request.
 */
class RequireSecurityTotp
{
    public function __construct(private SecurityTotpService $totp) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof \App\Models\User || ! $user->isSecurityAdmin() || ! $user->isActiveAccount()) {
            return $next($request);
        }

        if ($this->totp->needsEnrollment($user)) {
            if (! $request->routeIs('security.totp.enroll') && ! $request->routeIs('security.totp.confirm')) {
                return redirect()->route('security.totp.enroll');
            }

            return $next($request);
        }

        if (! $this->totp->isSessionVerified()
            && ! $request->routeIs('security.totp.challenge', 'security.totp.verify')) {
            return redirect()->route('security.totp.challenge');
        }

        return $next($request);
    }
}
