<?php

namespace App\Providers;

use App\Contracts\EditorialAiClient;
use App\Contracts\PhotoEnhancementClient;
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
    }
}
