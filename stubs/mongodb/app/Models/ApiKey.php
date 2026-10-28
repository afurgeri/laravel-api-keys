<?php

namespace App\Models;

use App\Crud\ApiKeyCrudDefinition;
use App\Policies\ApiKeyPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\ApiKeys\Models\MongoDB\ApiKey as BaseApiKey;
use Modules\Crud\Concerns\HasCrudDefinition;
use Modules\Crud\Contracts\HasCrudDefinition as HasCrudDefinitionContract;
use Modules\Rbac\Contracts\HasPermissions;
use Modules\Rbac\RbacModels;

/**
 * An API key whose permissions come from an RBAC role, so it works with the
 * "permission:<name>" middleware and with policies typed against HasPermissions.
 *
 * @property string|null $role_id
 * @property-read Model|null $role
 */
#[UsePolicy(ApiKeyPolicy::class)]
class ApiKey extends BaseApiKey implements HasCrudDefinitionContract, HasPermissions
{
    use HasCrudDefinition;

    /**
     * @var array<string, bool>
     */
    private array $permissionCache = [];

    public static function crudDefinition(): string
    {
        return ApiKeyCrudDefinition::class;
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(RbacModels::role(), 'role_id');
    }

    public function hasPermission(string $permission): bool
    {
        if (array_key_exists($permission, $this->permissionCache)) {
            return $this->permissionCache[$permission];
        }

        $role = $this->role;

        return $this->permissionCache[$permission] = $role !== null
            && method_exists($role, 'permissions')
            && $role->permissions()->where('name', $permission)->exists();
    }

    public function hasAbility(string $ability): bool
    {
        return $this->hasPermission($ability);
    }
}
