<?php

namespace App\Providers;

use App\Http\Middleware\ForceJson;
use App\Http\Middleware\RoleMiddleware;
use App\Support\CurrentBarangay;
use Illuminate\Support\ServiceProvider;

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
    }
}
