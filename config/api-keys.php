<?php

return [
    /*
    | Where keys are stored: "sql" (any SQL connection) or "mongodb". Without a
    | connection, Laravel's default connection is used. The driver must match
    | the connection type.
    */
    'storage' => [
        'driver' => env('API_KEYS_STORAGE', 'sql'),
        'connection' => env('API_KEYS_CONNECTION'),
    ],

    /*
    | Eloquent model used to store and authenticate keys. When null, the package
    | model for the storage driver is used. Applications may point this to a
    | subclass, for example to integrate roles and permissions.
    */
    'model' => null,

    'table' => 'api_keys',

    /*
    | Name of the guard registered by the package. Protect routes with
    | "auth:<guard>" (by default "auth:api-key").
    */
    'guard' => 'api-key',

    /*
    | Plain-text keys look like "<prefix>_<random>". The prefix helps identify
    | leaked keys in logs and secret scanners. The random part is never stored.
    */
    'prefix' => env('API_KEYS_PREFIX', 'ak'),

    'length' => 40,

    /*
    | Where keys are read from. "Authorization: Bearer <key>" is accepted when
    | "bearer" is true, in addition to the listed headers.
    */
    'bearer' => true,

    'headers' => ['X-Api-Key'],

    /*
    | Minimum seconds between "last_used_at" writes for the same key, to avoid
    | one database write per request.
    */
    'last_used_interval' => 60,

    /*
    | Requests per minute allowed for each key by the "api-keys" rate limiter.
    | Apply it with the "throttle:api-keys" middleware.
    */
    'rate_limit' => (int) env('API_KEYS_RATE_LIMIT', 60),
];
