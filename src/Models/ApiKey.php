<?php

namespace Modules\ApiKeys\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\ApiKeys\Concerns\InteractsWithApiKey;
use Modules\ApiKeys\Contracts\ApiKey as ApiKeyContract;

/**
 * @property int $id
 * @property string $name
 * @property string $prefix
 * @property string $key_hash
 * @property list<string>|null $abilities
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $status
 */
#[Fillable(['name', 'abilities', 'expires_at'])]
#[Hidden(['key_hash'])]
class ApiKey extends Model implements ApiKeyContract
{
    use InteractsWithApiKey;
}
