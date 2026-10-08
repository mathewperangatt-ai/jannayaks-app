<?php

namespace App\Providers;

use App\Contracts\EditorialAiClient;
use App\Contracts\PhotoEnhancementClient;
use App\Models\Application;
use App\Services\Ai\FakeEditorialAiClient;
use App\Services\Ai\FakePhotoEnhancementClient;
use App\Services\Ai\OpenAiEditorialClient;
use App\Services\Ai\OpenAiPhotoEnhancementClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EditorialAiClient::class, function ($app) {
            $provider = (string) config('jannayaks.ai.provider', 'gpt');

            if ($provider === 'fake' || $app->environment('testing')) {
                return $app->make(FakeEditorialAiClient::class);
            }

            return $app->make(OpenAiEditorialClient::class);
        });

        // Independent image-enhancement provider selection — deliberately
        // NOT coupled to the editorial AI provider above.
        $this->app->bind(PhotoEnhancementClient::class, function ($app) {
            $provider = (string) config('jannayaks.ai.image_enhancement.provider', 'fake');

            if ($provider === 'openai' && ! $app->environment('testing')) {
                return $app->make(OpenAiPhotoEnhancementClient::class);
            }

            return $app->make(FakePhotoEnhancementClient::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A1 — authed users hitting guest-only pages (e.g. /login) go to
        // their existing application dashboard, never a bare home page.
        \Illuminate\Auth\Middleware\RedirectIfAuthenticated::redirectUsing(function ($request) {
            $user = $request->user();

            if ($user) {
                $application = Application::query()
                    ->where('user_id', $user->id)
                    ->orderByDesc('id')
                    ->first();

                if ($application !== null) {
                    return route('applications.show', $application);
                }
            }

            return route('apply');
        });

        // Named, per-user limiters for authenticated write endpoints. Plain
        // `throttle:N,M` shares one counter per IP across ALL routes — the
        // autosave budget would silently exhaust the submit/payment budgets
        // of every member behind the same address. Per-user keys isolate
        // routes and survive shared-NAT households.
        \Illuminate\Support\Facades\RateLimiter::for('application-create', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('app-create:'.$request->user()?->id ?: $request->ip());
        });
        \Illuminate\Support\Facades\RateLimiter::for('source-upload', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('src-upload:'.$request->user()?->id ?: $request->ip());
        });
        \Illuminate\Support\Facades\RateLimiter::for('interview-save', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by('int-save:'.$request->user()?->id ?: $request->ip());
        });
        \Illuminate\Support\Facades\RateLimiter::for('interview-submit', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('int-submit:'.$request->user()?->id ?: $request->ip());
        });
        \Illuminate\Support\Facades\RateLimiter::for('payment-initiate', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('pay-init:'.$request->user()?->id ?: $request->ip());
        });

        // S2 — password strength baseline. Password::defaults() was never
        // configured, so registration and reset accepted any non-empty
        // password. Length + letters + numbers keeps friction low for the
        // member demographic; no HIBP network dependency. (The per-username
        // login throttle lives in LoginController::authenticate — it must
        // count FAILED attempts per credential, which middleware cannot do.)
        \Illuminate\Validation\Rules\Password::defaults(function () {
            return \Illuminate\Validation\Rules\Password::min(10)->letters()->numbers();
        });
    }
}
