<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('webhook-capture', function (Request $request) {
            $token = $request->route('token');
            $key = 'wc:'.$request->ip().':'.(is_string($token) ? $token : '');

            // Per-endpoint + per-IP: one noisy sender must not starve others.
            return Limit::perMinute(120)
                ->by($key)
                ->response(function () {
                    return response()->json(['error' => 'rate limited'], 429);
                });
        });
    }
}
