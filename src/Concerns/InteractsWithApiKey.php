<?php

namespace Modules\ApiKeys\Concerns;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Modules\ApiKeys\Contracts\ApiKey;

/**
 * Shared behavior of the SQL and MongoDB API key models.
 *
 * @property string $name
 * @property string $prefix
 * @property string $key_hash
 * @property list<string>|null $abilities
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property-read string $status
 */
trait InteractsWithApiKey
{
    use Authenticatable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return (string) config('api-keys.table', 'api_keys');
    }

    public function getConnectionName(): ?string
    {
        return config('api-keys.storage.connection') ?: parent::getConnectionName();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    /**
     * @return Attribute<string, never>
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn (): string => match (true) {
            $this->isRevoked() => ApiKey::STATUS_REVOKED,
            $this->isExpired() => ApiKey::STATUS_EXPIRED,
            default => ApiKey::STATUS_ACTIVE,
        });
    }

    /**
     * Abilities stored on the key. "*" grants every ability. Subclasses may
     * override this to resolve abilities from another source, such as roles.
     */
    public function hasAbility(string $ability): bool
    {
        $abilities = $this->abilities ?? [];

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    /**
     * API keys never authenticate with a password or a remember token.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
