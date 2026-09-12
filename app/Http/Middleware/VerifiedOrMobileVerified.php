<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifiedOrMobileVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => 'Login required.'], 401);
            }
            return redirect()->route('login');
        }

        $user = Auth::user();
        $emailOk   = $user && isset($user->email_verified_at) && $user->email_verified_at !== null;
        $mobileOk  = $user && isset($user->mobile_verified_at) && $user->mobile_verified_at !== null;

        if (! $emailOk && ! $mobileOk) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'Please verify your email address or mobile number before continuing.',
                ], 403);
            }
            $loginRoute = app()->routesAreCached()
                ? 'filament.admin.auth.login'
                : (app('router')->has('filament.admin.auth.login') ? 'filament.admin.auth.login' : 'home');
            return redirect()->route($loginRoute)
                ->withErrors(['verify' => 'Please verify your email or mobile number before continuing.']);
        }

        return $next($request);
    }
}
