<?php

namespace Modules\ApiKeys\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\ApiKeys\Contracts\ApiKey;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKeyHasAbility
{
    /**
     * Require every listed ability: "api-key.ability:clients.view,invoices.view".
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $apiKey = $request->user();

        if (! $apiKey instanceof ApiKey) {
            abort(401);
        }

        foreach ($abilities as $ability) {
            if (! $apiKey->hasAbility($ability)) {
                abort(403);
            }
        }

        return $next($request);
    }
}
