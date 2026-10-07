<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Limits on the endpoints that cost money or leak information when hammered.
 *
 * Generous enough that no honest learner meets them: a student asking six
 * questions in a minute is not studying, and search is capped per minute rather
 * than per request so typing ahead never trips it.
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Costs real money per call
        RateLimiter::for('assistant', fn (Request $request) => Limit::perMinute(6)
            ->by($request->user()?->id ?: $request->ip())
            ->response(fn () => back()->withErrors([
                'question' => 'Give it a moment before asking again.',
            ])));

        // Cheap but database-heavy, and typed quickly by design
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Creating work for tutors
        RateLimiter::for('requests', fn (Request $request) => Limit::perMinute(5)
            ->by($request->user()?->id ?: $request->ip()));

        // Sending messages and codes costs money per send
        RateLimiter::for('messaging', fn (Request $request) => Limit::perMinute(20)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('codes', fn (Request $request) => Limit::perMinute(3)
            ->by($request->user()?->id ?: $request->ip()));

        // Uploads: bandwidth and storage
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip()));

        // Anything PayFast or a gateway calls us on
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
}
