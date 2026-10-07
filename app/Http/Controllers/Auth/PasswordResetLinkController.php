<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Email-based password reset (request link). Responses are deliberately
 * generic — no account enumeration.
 */
class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // We do not reset accounts that cannot authenticate anyway. Look up
        // quietly and only send when the account exists with a set password —
        // but always return the same generic status to the caller.
        $user = User::query()->where('email', strtolower((string) $request->input('email')))->first();

        if ($user !== null && $user->password !== null) {
            $status = Password::sendResetLink($request->only('email'));
        } else {
            $status = Password::RESET_LINK_SENT;
        }

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with(['status' => __($status)]);
        }

        throw ValidationException::withMessages([
            'email' => trans($status),
        ]);
    }
}
