<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $input = $request->input('email', '');
            $email = is_string($input)
                ? Str::transliterate(Str::lower($input))
                : 'invalid-email';

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by(
                (string) $request->session()->get('login.id', $request->ip())
            );
        });
    }
}
