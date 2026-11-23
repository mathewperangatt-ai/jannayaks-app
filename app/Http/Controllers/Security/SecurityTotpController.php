<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Security\SecurityTotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * TOTP enrollment and per-login challenge for the Jannayaks Security
 * Administrator. Standalone pages, styled minimally; never reachable by
 * unauthenticated users (auth + the panel middleware chain apply).
 */
class SecurityTotpController extends Controller
{
    public function __construct(private SecurityTotpService $totp) {}

    public function enroll(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isSecurityAdmin(), 403);

        // Re-enroll (or first enroll): generates fresh secret + recovery codes.
        // Codes are displayed exactly once on this screen.
        $enrollment = $this->totp->enroll($user);

        return view('security.totp-enroll', [
            'secret' => $enrollment['secret'],
            'uri' => $enrollment['uri'],
            'recoveryCodes' => $enrollment['recovery_codes'],
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();
        abort_unless($user instanceof User && $user->isSecurityAdmin(), 403);

        if (! $this->totp->confirmEnrollment($user, (string) $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => 'That code did not match. Check your authenticator clock and try again.',
            ]);
        }

        return redirect()->intended('/admin');
    }

    public function challenge(): View
    {
        return view('security.totp-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();
        abort_unless($user instanceof User && $user->isSecurityAdmin(), 403);

        // Brute-force guard: 5 attempts per minute per session.
        if (RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Wait a minute and try again.',
            ]);
        }

        if (! $this->totp->verifyChallenge($user, (string) $validated['code'])) {
            RateLimiter::hit($this->throttleKey($request), 60);

            throw ValidationException::withMessages([
                'code' => 'That code is not valid.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        return redirect()->intended('/admin');
    }

    private function throttleKey(Request $request): string
    {
        // Bound to the ACCOUNT, not the session: brute-force attempts against
        // the Security Administrator credential throttle regardless of how
        // many sessions or IPs the attacker rotates through.
        return 'security-totp:'.($request->user()?->id ?? $request->ip());
    }
}
