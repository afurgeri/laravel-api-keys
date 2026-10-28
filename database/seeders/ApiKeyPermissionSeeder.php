<?php

namespace Modules\ApiKeys\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\ApiKeys\Permissions\ApiKeyPermissions;
use Modules\Rbac\RbacModels;

class ApiKeyPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissionModel = RbacModels::permission();

        foreach (ApiKeyPermissions::all() as $permission) {
            $attributes = ['name' => $permission];

            if (config('rbac.storage', 'mysql') === 'mongodb') {
                $attributes['role_ids'] = [];
            }

            $permissionModel::updateOrCreate(['name' => $permission], $attributes);
        }
    }
}
