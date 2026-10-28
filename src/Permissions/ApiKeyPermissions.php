<?php

namespace Modules\ApiKeys\Permissions;

final class ApiKeyPermissions
{
    public const VIEW = 'api-keys.view';

    public const CREATE = 'api-keys.create';

    public const ROTATE = 'api-keys.rotate';

    public const REVOKE = 'api-keys.revoke';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::VIEW,
            self::CREATE,
            self::ROTATE,
            self::REVOKE,
        ];
    }
}
