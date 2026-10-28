<?php

namespace App\Policies;

use App\Models\ApiKey;
use Modules\ApiKeys\Contracts\ApiKey as ApiKeyContract;
use Modules\ApiKeys\Permissions\ApiKeyPermissions;
use Modules\Rbac\Contracts\HasPermissions;

/**
 * Only people manage keys: a request authenticated with an API key is always refused.
 */
class ApiKeyPolicy
{
    public function viewAny(HasPermissions $user): bool
    {
        return ! $user instanceof ApiKeyContract && $user->hasPermission(ApiKeyPermissions::VIEW);
    }

    public function view(HasPermissions $user, ApiKey $apiKey): bool
    {
        return $this->viewAny($user);
    }

    public function create(HasPermissions $user): bool
    {
        return ! $user instanceof ApiKeyContract && $user->hasPermission(ApiKeyPermissions::CREATE);
    }

    public function update(HasPermissions $user, ApiKey $apiKey): bool
    {
        return false;
    }

    public function rotate(HasPermissions $user, ApiKey $apiKey): bool
    {
        return ! $user instanceof ApiKeyContract
            && ! $apiKey->isRevoked()
            && $user->hasPermission(ApiKeyPermissions::ROTATE);
    }

    public function delete(HasPermissions $user, ApiKey $apiKey): bool
    {
        return ! $user instanceof ApiKeyContract
            && ! $apiKey->isRevoked()
            && $user->hasPermission(ApiKeyPermissions::REVOKE);
    }
}
