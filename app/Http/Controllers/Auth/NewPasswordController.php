<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Set a new password from an emailed reset token.
 */
class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        // Fail closed for suspended accounts: a suspension must not be
        // worked around by resetting credentials. Generic message — no
        // account-state enumeration.
        $target = User::query()
            ->where('email', strtolower((string) $validated['email']))
            ->first();

        if ($target !== null && $target->account_status !== 'active') {
            throw ValidationException::withMessages([
                'email' => trans(Password::INVALID_USER),
            ]);
        }

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $user = User::query()->where('email', $validated['email'])->first();
            if ($user !== null && $user->isActiveAccount()) {
                Auth::login($user);
                $request->session()->regenerate();

                return redirect()->intended(route('home'))->with('status', __($status));
            }

            return redirect()->route('login')->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => trans($status),
        ]);
    }
}
