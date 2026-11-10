<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('signup', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(20)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('account:'.hash('sha256', mb_strtolower(trim((string) $request->input('email')))).'|'.$request->ip()),
        ]);
    }
}
