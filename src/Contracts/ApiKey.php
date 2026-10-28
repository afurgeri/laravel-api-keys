<?php

namespace Modules\ApiKeys\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Implemented by the SQL and MongoDB API key models, so guards, middleware and
 * policies do not depend on the storage driver.
 */
interface ApiKey extends Authenticatable
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    public function hasAbility(string $ability): bool;

    public function isRevoked(): bool;

    public function isExpired(): bool;

    public function isActive(): bool;
}
