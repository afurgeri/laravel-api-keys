<?php

namespace Modules\ApiKeys;

use Illuminate\Database\Eloquent\Model;
use Modules\ApiKeys\Contracts\ApiKey;

/**
 * A freshly issued key. The plain-text value is only available here: it is
 * never stored, so it must be shown to the user immediately.
 */
final readonly class NewApiKey
{
    public function __construct(
        public Model&ApiKey $apiKey,
        public string $plainTextKey,
    ) {}
}
