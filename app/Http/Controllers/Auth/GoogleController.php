<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SafeInternalUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse|\Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors([
                'google' => 'Google sign-in failed. Please try again.',
            ]);
        }

        $googleId = (string) $googleUser->getId();
        $email = $googleUser->getEmail();
        $name = $googleUser->getName() ?: 'Jannayaks Member';

        if (! is_string($email) || trim($email) === '') {
            return redirect()->route('login')->withErrors([
                'google' => 'Google did not provide an email address. Please use a different Google account.',
            ]);
        }

        $email = strtolower(trim($email));

        $byGoogle = User::query()->where('google_id', $googleId)->first();
        if ($byGoogle instanceof User) {
            $byGoogle->fill(['name' => $name]);
            if ($byGoogle->email_verified_at === null) {
                $byGoogle->email_verified_at = now();
            }
            if ($byGoogle->email === null || $byGoogle->email === '') {
                $byGoogle->email = $email;
            }
            $byGoogle->save();

            return $this->completeLogin($byGoogle);
        }

        $byEmail = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($byEmail instanceof User) {
            if ($byEmail->email_verified_at === null) {
                return redirect()->route('login')->withErrors([
                    'google' => 'This email belongs to an unverified account and cannot be linked automatically. Sign in with the original method or contact support.',
                ]);
            }

            if ($byEmail->google_id !== null && $byEmail->google_id !== '' && $byEmail->google_id !== $googleId) {
                return redirect()->route('login')->withErrors([
                    'google' => 'This email is already linked to a different Google account. Sign-in was blocked for safety.',
                ]);
            }

            $byEmail->fill([
                'google_id' => $googleId,
                'name' => $name,
            ]);
            $byEmail->save();

            return $this->completeLogin($byEmail);
        }

        $user = new User;
        $user->forceFill([
            'google_id' => $googleId,
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            'role' => User::ROLE_MEMBER,
            'account_status' => 'active',
            'password' => null,
        ])->save();

        return $this->completeLogin($user);
    }

    private function completeLogin(User $user): RedirectResponse
    {
        if (! $user->isActiveAccount()) {
            return redirect()->route('login')->withErrors([
                'google' => 'This account is suspended and cannot sign in. Contact support for assistance.',
            ]);
        }

        // Bounded remember-me: 30 days instead of Laravel's ~5-year default.
        Auth::guard()->setRememberDuration(60 * 24 * 30);
        Auth::login($user, remember: true);
        request()->session()->regenerate();

        if (session()->has('apply.intent')) {
            return redirect()->route('apply.continue');
        }

        $fallback = route('apply');
        $intended = SafeInternalUrl::intended(session()->pull('url.intended'));
        if ($intended !== null) {
            return redirect()->to($intended);
        }

        return redirect()->to($fallback);
    }
}
