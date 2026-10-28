<?php

namespace Modules\ApiKeys;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\ApiKeys\Console\Commands\InstallApiKeysCommand;
use Modules\ApiKeys\Contracts\ApiKey;
use Modules\ApiKeys\Http\Middleware\EnsureApiKeyHasAbility;

class ApiKeysServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/api-keys.php', 'api-keys');
        $this->app->singleton(ApiKeys::class);
    }

    public function boot(): void
    {
        $guard = (string) config('api-keys.guard', 'api-key');

        if (config("auth.guards.{$guard}") === null) {
            config(["auth.guards.{$guard}" => ['driver' => 'api-key', 'provider' => null]]);
        }

        Auth::viaRequest('api-key', fn (Request $request): ?ApiKey => $this->app->make(ApiKeys::class)->authenticate($request));

        Route::aliasMiddleware('api-key.ability', EnsureApiKeyHasAbility::class);

        RateLimiter::for('api-keys', fn (Request $request): Limit => Limit::perMinute((int) config('api-keys.rate_limit', 60))
            ->by($request->user() instanceof ApiKey ? 'api-key:'.$request->user()->getKey() : 'ip:'.$request->ip()));

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([__DIR__.'/../config/api-keys.php' => config_path('api-keys.php')], 'api-keys-config');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallApiKeysCommand::class]);
        }
    }
}
