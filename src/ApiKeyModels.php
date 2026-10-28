<?php

namespace Modules\ApiKeys;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Modules\ApiKeys\Contracts\ApiKey;
use RuntimeException;

final class ApiKeyModels
{
    public static function driver(): string
    {
        $driver = config('api-keys.storage.driver', 'sql');
        $connection = config('api-keys.storage.connection') ?: config('database.default');
        $connectionDriver = config("database.connections.{$connection}.driver");

        if (! in_array($driver, ['sql', 'mongodb'], true)) {
            throw new InvalidArgumentException('API keys storage must be sql or mongodb.');
        }

        if (($driver === 'mongodb') !== ($connectionDriver === 'mongodb')) {
            throw new InvalidArgumentException('API keys storage driver does not match its database connection.');
        }

        if ($driver === 'mongodb' && ! class_exists(\MongoDB\Laravel\Eloquent\Model::class)) {
            throw new RuntimeException('Install mongodb/laravel-mongodb and ext-mongodb to store API keys in MongoDB.');
        }

        return $driver;
    }

    /**
     * @return class-string<Model&ApiKey>
     */
    public static function key(): string
    {
        $model = config('api-keys.model') ?: (self::driver() === 'mongodb'
            ? Models\MongoDB\ApiKey::class
            : Models\ApiKey::class);

        if (! is_string($model) || ! is_subclass_of($model, Model::class) || ! is_subclass_of($model, ApiKey::class)) {
            throw new InvalidArgumentException('The configured API key model must be an Eloquent model implementing '.ApiKey::class.'.');
        }

        /** @var class-string<Model&ApiKey> $model */
        return $model;
    }
}
