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

        if ($user instanceof \App\Models\User && ! $user->isActiveAccount()) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'error' => 'This account is suspended and cannot be used. Contact support.',
                ], 403);
            }

            return redirect()->route('login')
                ->withErrors(['account' => 'This account is suspended and cannot be used. Contact support.']);
        }

        $emailOk = $user && isset($user->email_verified_at) && $user->email_verified_at !== null;
        $mobileOk = $user && isset($user->mobile_verified_at) && $user->mobile_verified_at !== null;

        if (! $emailOk && ! $mobileOk) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Please verify your email address or mobile number before continuing.',
                ], 403);
            }

            return redirect()->route('login')
                ->withErrors(['verify' => 'Please verify your email or mobile number before continuing.']);
        }

        return $next($request);
    }
}
