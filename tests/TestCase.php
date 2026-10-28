<?php

namespace ApiKeysTests;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Modules\ApiKeys\ApiKeysServiceProvider;
use MongoDB\Laravel\MongoDBServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [MongoDBServiceProvider::class, ApiKeysServiceProvider::class];
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->middleware('auth:api-key')->group(function (Router $router): void {
            $router->get('api/me', fn () => ['id' => (string) request()->user()->getKey()]);
            $router->get('api/clients', fn () => ['ok' => true])->middleware('api-key.ability:clients.view');
            $router->get('api/limited', fn () => ['ok' => true])->middleware('throttle:api-keys');
        });
    }

    /**
     * Point the package to the given storage and create its schema. MongoDB tests
     * use a random database that is dropped afterwards; they are skipped unless
     * MONGODB_TEST_URI is set.
     */
    protected function useStorage(string $driver): void
    {
        if ($driver === 'mongodb' && ! getenv('MONGODB_TEST_URI')) {
            $this->markTestSkipped('Set MONGODB_TEST_URI to run against an isolated MongoDB test database.');
        }

        config([
            'api-keys.storage.driver' => $driver,
            'api-keys.storage.connection' => $driver === 'mongodb' ? 'api_keys_test' : 'sqlite',
            'database.connections.api_keys_test' => [
                'driver' => 'mongodb',
                'dsn' => getenv('MONGODB_TEST_URI') ?: 'mongodb://127.0.0.1:27017',
                'database' => 'api_keys_test_'.bin2hex(random_bytes(8)),
            ],
        ]);

        foreach (glob(__DIR__.'/../database/migrations/*.php') as $file) {
            (require $file)->up();
        }

        if ($driver === 'mongodb') {
            $this->beforeApplicationDestroyed(fn () => DB::connection('api_keys_test')->getDatabase()->drop());
        }
    }
}
