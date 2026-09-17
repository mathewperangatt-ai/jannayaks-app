<?php

namespace App\Providers;

use App\Contracts\EditorialAiClient;
use App\Services\Ai\FakeEditorialAiClient;
use App\Services\Ai\OpenAiEditorialClient;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
