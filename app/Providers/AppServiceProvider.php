<?php

namespace App\Providers;

use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Notification::extend('expoPush', fn () => new ExpoPushChannel());

        // Limitadores por rota e por conta (o `throttle:N,M` partilha o contador entre rotas).
        $perEmail = fn (string $name, int $perMinute) => RateLimiter::for(
            $name,
            fn (Request $request) => Limit::perMinute($perMinute)->by($name . '|' . $request->ip() . '|' . strtolower((string) $request->input('email')))
        );
        $perEmail('auth-login', 10);
        $perEmail('auth-register', 5);
        $perEmail('auth-otp', 5);
        $perEmail('auth-otp-resend', 3);
        $perEmail('auth-password-forgot', 3);
        $perEmail('auth-password-reset', 5);

        // Super Admin KIVARA: bypass total de permissões Spatie
        Gate::before(function ($user, string $ability) {
            if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
                return true;
            }

            return null;
        });
    }
}
