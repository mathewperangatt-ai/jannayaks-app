<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * Customer registration: username + email + password.
 * Email is marked verified at registration (possession proven by receiving
 * the welcome/reset mail at that address); suspension rules unchanged.
 */
class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $return = $request->query('return');
        if (is_string($return) && $return !== '') {
            session()->put('url.intended', $return);
        }

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        // Wave 3 follow-up: duplicate identification is explicit and
        // field-specific — the applicant is told whether the USERNAME or the
        // EMAIL is already registered (or both). Personal names are never a
        // uniqueness criterion: two applicants may share the same name.
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9])?$/', 'unique:users,username'],
            'email' => ['required', 'string', 'email:strict', 'max:255', 'unique:users,email', function (string $attribute, mixed $value, \Closure $fail): void {
                if (Application::emailIsAssociated((string) $value)) {
                    $fail('This email address is already associated with an existing application.');
                }
            }],
            'password' => ['required', 'string', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ], [
            'username.unique' => 'This username is already registered. Please choose a different username.',
            'email.unique' => 'This email address is already registered. Please sign in instead.',
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'username' => strtolower($validated['username']),
            'email' => strtolower($validated['email']),
            'email_verified_at' => now(),
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_MEMBER,
            'account_status' => 'active',
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        // A-flow — a registration that started from the tier/intent form
        // continues straight into the pending application (payment next),
        // instead of dropping the customer back on the tier page.
        if ($request->session()->has('apply.intent')) {
            return app(\App\Http\Controllers\ApplicationController::class)
                ->continueFromIntent($request)
                ->with('status', 'Welcome to Jannayaks! Your account has been created — complete payment to continue.');
        }

        return redirect()->intended(route('apply'))
            ->with('status', 'Welcome to Jannayaks! Your account has been created.');
    }
}
