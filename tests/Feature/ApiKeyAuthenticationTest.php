<?php

use Modules\ApiKeys\ApiKeyModels;
use Modules\ApiKeys\ApiKeys;

test('authenticates a request with the key in the X-Api-Key header or as a bearer token', function (string $driver) {
    $this->useStorage($driver);
    $newApiKey = app(ApiKeys::class)->create('Reporting');
    $id = (string) $newApiKey->apiKey->getKey();

    $this->getJson('/api/me', ['X-Api-Key' => $newApiKey->plainTextKey])
        ->assertOk()->assertExactJson(['id' => $id]);
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/me', ['Authorization' => 'Bearer '.$newApiKey->plainTextKey])
        ->assertOk()->assertExactJson(['id' => $id]);
})->with('storage');

test('stores only a hash and a short visible prefix of the key', function (string $driver) {
    $this->useStorage($driver);

    $newApiKey = app(ApiKeys::class)->create('Reporting');

    $stored = ApiKeyModels::key()::query()->sole();
    expect($newApiKey->plainTextKey)->toStartWith('ak_')
        ->and(json_encode($stored->getAttributes()))->not->toContain($newApiKey->plainTextKey)
        ->and($stored->getAttribute('key_hash'))->toBe(hash('sha256', $newApiKey->plainTextKey))
        ->and($newApiKey->plainTextKey)->toStartWith($stored->getAttribute('prefix'))
        ->and(strlen($stored->getAttribute('prefix')))->toBeLessThan(16)
        ->and($stored->toArray())->not->toHaveKey('key_hash');
})->with('storage');

test('rejects requests without a valid active key', function (string $driver, string $case) {
    $this->useStorage($driver);
    $keys = app(ApiKeys::class);

    $headers = match ($case) {
        'no key' => [],
        'unknown key' => ['X-Api-Key' => 'ak_'.str_repeat('x', 40)],
        'key with another prefix' => ['X-Api-Key' => 'zz_'.substr($keys->create('Other')->plainTextKey, 3)],
        'revoked key' => (function () use ($keys): array {
            $newApiKey = $keys->create('Revoked');
            $keys->revoke($newApiKey->apiKey);

            return ['X-Api-Key' => $newApiKey->plainTextKey];
        })(),
        'expired key' => ['X-Api-Key' => $keys->create('Expired', expiresAt: now()->subMinute())->plainTextKey],
    };

    $this->getJson('/api/me', $headers)->assertUnauthorized();
})->with('storage')->with(['no key', 'unknown key', 'key with another prefix', 'revoked key', 'expired key']);

test('rotation issues a new key and the previous one stops working', function (string $driver) {
    $this->useStorage($driver);
    $keys = app(ApiKeys::class);
    $original = $keys->create('Reporting');

    $rotated = $keys->rotate($original->apiKey);

    expect($rotated->plainTextKey)->not->toBe($original->plainTextKey)
        ->and(ApiKeyModels::key()::query()->count())->toBe(1);
    $this->getJson('/api/me', ['X-Api-Key' => $original->plainTextKey])->assertUnauthorized();
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/me', ['X-Api-Key' => $rotated->plainTextKey])->assertOk();
})->with('storage');

test('the ability middleware requires every listed ability', function (string $driver, array $abilities, int $status) {
    $this->useStorage($driver);
    $newApiKey = app(ApiKeys::class)->create('Reporting', $abilities);

    $this->getJson('/api/clients', ['X-Api-Key' => $newApiKey->plainTextKey])->assertStatus($status);
})->with('storage')->with([
    'without abilities' => [[], 403],
    'with another ability' => [['invoices.view'], 403],
    'with the ability' => [['clients.view'], 200],
    'with every ability' => [['*'], 200],
]);

test('records the last use at most once per configured interval', function (string $driver) {
    $this->useStorage($driver);
    $this->freezeSecond();
    $newApiKey = app(ApiKeys::class)->create('Reporting');
    $lastUsedAt = fn () => ApiKeyModels::key()::query()->sole()->getAttribute('last_used_at');

    $this->getJson('/api/me', ['X-Api-Key' => $newApiKey->plainTextKey])->assertOk();
    $firstUse = $lastUsedAt();
    $this->travel(30)->seconds();
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/me', ['X-Api-Key' => $newApiKey->plainTextKey])->assertOk();
    $afterThirtySeconds = $lastUsedAt();
    $this->travel(31)->seconds();
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/me', ['X-Api-Key' => $newApiKey->plainTextKey])->assertOk();

    expect($firstUse->equalTo($afterThirtySeconds))->toBeTrue()
        ->and($lastUsedAt()->greaterThan($firstUse))->toBeTrue();
})->with('storage');

test('bearer tokens are ignored when disabled', function () {
    $this->useStorage('sql');
    config(['api-keys.bearer' => false]);
    $newApiKey = app(ApiKeys::class)->create('Reporting');

    $this->getJson('/api/me', ['Authorization' => 'Bearer '.$newApiKey->plainTextKey])->assertUnauthorized();
});

test('the rate limiter counts each key separately', function () {
    $this->useStorage('sql');
    config(['api-keys.rate_limit' => 1]);
    $keys = app(ApiKeys::class);
    $first = $keys->create('First');
    $second = $keys->create('Second');

    $this->getJson('/api/limited', ['X-Api-Key' => $first->plainTextKey])->assertOk();
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/limited', ['X-Api-Key' => $first->plainTextKey])->assertTooManyRequests();
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/limited', ['X-Api-Key' => $second->plainTextKey])->assertOk();
});

test('refuses a storage driver that does not match its connection', function () {
    config(['api-keys.storage.driver' => 'mongodb', 'api-keys.storage.connection' => 'sqlite']);

    ApiKeyModels::key();
})->throws(InvalidArgumentException::class, 'does not match');
