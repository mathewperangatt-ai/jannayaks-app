<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\User;
use App\Support\SafeInternalUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Username + password customer login (primary authentication).
 * Google remains available as an optional convenience login.
 */
class LoginController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect($this->dashboardUrlFor(Auth::user()));
        }

        $return = SafeInternalUrl::intended($request->query('return'));
        if ($return !== null) {
            $request->session()->put('url.intended', $return);
        }

        return view('auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // S2 — per-credential throttle: bounds distributed attempts against a
        // single username even when the attacker rotates source IPs. Generous
        // window; cleared on successful login.
        $usernameKey = 'login-username:'.strtolower(trim((string) $credentials['username']));
        if (! RateLimiter::tooManyAttempts($usernameKey, 30)) {
            RateLimiter::hit($usernameKey, 60);
        } else {
            throw ValidationException::withMessages([
                'username' => 'Too many sign-in attempts. Please wait a minute and try again.',
            ]);
        }

        $remember = (bool) $request->boolean('remember');

        if (! Auth::attempt([
            'username' => $credentials['username'],
            'password' => $credentials['password'],
        ], $remember)) {
            throw ValidationException::withMessages([
                'username' => 'Invalid username or password.',
            ]);
        }

        $user = Auth::user();

        // Suspension is re-checked after a successful credential match so a
        // suspended account can never authenticate, even with valid passwords.
        if (! $user->isActiveAccount()) {
            Auth::logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'username' => 'This account is suspended and cannot sign in. Contact support.',
            ]);
        }

        $request->session()->regenerate();

        RateLimiter::clear($usernameKey);

        // A guest who picked a tier before signing in continues straight
        // into application creation (payment next). Do not rely on
        // url.intended here: SafeInternalUrl drops cross-host return URLs,
        // so the pending intent in the session is the durable signal.
        if ($request->session()->has('apply.intent')) {
            return app(\App\Http\Controllers\ApplicationController::class)
                ->continueFromIntent($request);
        }

        return redirect()->intended($this->dashboardUrlFor($user));
    }

    /** A1 — returning customers land on their existing application dashboard. */
    private function dashboardUrlFor(User $user): string
    {
        $application = Application::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        return $application !== null
            ? route('applications.show', $application)
            : route('apply');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
