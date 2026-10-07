<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Swap this binding when DX chooses a transcription provider.
        $this->app->bind(
            \App\Domains\Voice\Services\Transcriber::class,
            \App\Domains\Voice\Services\NullTranscriber::class,
        );

        // Replaced by a real provider once DX configures one in admin settings.
        $this->app->bind(
            \App\Domains\Assistant\Services\AssistantProvider::class,
            \App\Domains\Assistant\Services\NullAssistantProvider::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mail provider comes from admin settings rather than .env
        if (! $this->app->runningInConsole() || $this->app->runningUnitTests() === false) {
            try {
                app(\App\Domains\Messaging\Services\MessageDispatcher::class)->applyMailConfig();
            } catch (\Throwable) {
                // Falls back to the framework default before the table exists
            }
        }

        Vite::prefetch(concurrency: 3);
    }
}
