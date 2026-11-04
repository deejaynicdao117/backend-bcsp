<?php

namespace App\Providers;

use App\Http\Middleware\ForceJson;
use App\Http\Middleware\RoleMiddleware;
use App\Support\CurrentBarangay;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentBarangay::class);
    }

    public function boot(): void
    {
        $this->app['router']->aliasMiddleware('role', RoleMiddleware::class);
        $this->app['router']->aliasMiddleware('force.json', ForceJson::class);

        RateLimiter::for('login', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by('login:'.$request->ip().'|'.$email),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('registration', fn (Request $request): Limit => Limit::perMinute(5)->by('registration:'.$request->ip())
        );
    }
}
