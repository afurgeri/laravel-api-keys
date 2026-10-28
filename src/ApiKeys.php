<?php

namespace Modules\ApiKeys;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\ApiKeys\Contracts\ApiKey;

class ApiKeys
{
    /**
     * @param  list<string>  $abilities
     * @param  array<string, mixed>  $attributes  Extra server-controlled attributes, such as a role reference.
     */
    public function create(
        string $name,
        array $abilities = [],
        ?DateTimeInterface $expiresAt = null,
        ?Model $owner = null,
        array $attributes = [],
    ): NewApiKey {
        $model = ApiKeyModels::key();
        [$plainTextKey, $prefix, $hash] = $this->generate();

        $apiKey = new $model;
        $apiKey->forceFill([
            ...$attributes,
            'name' => $name,
            'abilities' => $abilities === [] ? null : array_values($abilities),
            'expires_at' => $expiresAt,
            'prefix' => $prefix,
            'key_hash' => $hash,
        ]);
        $apiKey->owner()->associate($owner);
        $apiKey->save();

        return new NewApiKey($apiKey, $plainTextKey);
    }

    /**
     * Replace the secret of an existing key. The previous value stops working immediately.
     */
    public function rotate(Model&ApiKey $apiKey): NewApiKey
    {
        [$plainTextKey, $prefix, $hash] = $this->generate();

        $apiKey->forceFill([
            'prefix' => $prefix,
            'key_hash' => $hash,
            'last_used_at' => null,
        ])->save();

        return new NewApiKey($apiKey, $plainTextKey);
    }

    public function revoke(Model&ApiKey $apiKey): void
    {
        if ($apiKey->isRevoked()) {
            return;
        }

        $apiKey->forceFill(['revoked_at' => now()])->save();
    }

    public function find(string $plainTextKey): (Model&ApiKey)|null
    {
        if (! str_starts_with($plainTextKey, $this->prefix().'_')) {
            return null;
        }

        return ApiKeyModels::key()::query()
            ->active()
            ->where('key_hash', $this->hash($plainTextKey))
            ->first();
    }

    /**
     * Resolve the key sent with the request, recording when it was last used.
     */
    public function authenticate(Request $request): (Model&ApiKey)|null
    {
        $plainTextKey = $this->keyFromRequest($request);

        if ($plainTextKey === null) {
            return null;
        }

        $apiKey = $this->find($plainTextKey);

        if ($apiKey !== null && $this->shouldRecordUsage($apiKey)) {
            $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $apiKey;
    }

    public function keyFromRequest(Request $request): ?string
    {
        foreach ((array) config('api-keys.headers', ['X-Api-Key']) as $header) {
            $value = $request->header((string) $header);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        if (config('api-keys.bearer', true)) {
            return $request->bearerToken() ?: null;
        }

        return null;
    }

    /**
     * @return array{string, string, string}
     */
    private function generate(): array
    {
        $plainTextKey = $this->prefix().'_'.Str::random(max(32, (int) config('api-keys.length', 40)));

        return [
            $plainTextKey,
            Str::substr($plainTextKey, 0, Str::length($this->prefix()) + 9),
            $this->hash($plainTextKey),
        ];
    }

    private function hash(string $plainTextKey): string
    {
        return hash('sha256', $plainTextKey);
    }

    private function prefix(): string
    {
        return (string) config('api-keys.prefix', 'ak');
    }

    private function shouldRecordUsage(Model&ApiKey $apiKey): bool
    {
        $lastUsedAt = $apiKey->getAttribute('last_used_at');

        return $lastUsedAt === null
            || $lastUsedAt->lte(now()->subSeconds((int) config('api-keys.last_used_interval', 60)));
    }
}
