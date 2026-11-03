<?php

use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Middleware\VerifiedOrMobileVerified;
use App\Http\Middleware\VerifyHostingIntegrationSignature;
use App\Support\TrustedProxiesConfig;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Throttle first so invalid/missing signatures still consume the limit.
            Route::middleware([
                'throttle:hosting-integration',
                VerifyHostingIntegrationSignature::class,
            ])
                ->prefix('internal/hosting')
                ->group(base_path('routes/hosting.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: TrustedProxiesConfig::fromEnv());
        $middleware->append(SetSecurityHeaders::class);
        $middleware->alias([
            'verified.or.mobile' => VerifiedOrMobileVerified::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->validateCsrfTokens(except: [
            'payments/razorpay/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->is('internal/hosting/*')
                || $request->expectsJson(),
        );
    })->create();
