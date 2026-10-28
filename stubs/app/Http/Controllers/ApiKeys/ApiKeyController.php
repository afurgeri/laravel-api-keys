<?php

namespace App\Http\Controllers\ApiKeys;

use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ApiKeys\ApiKeys;
use Modules\ApiKeys\NewApiKey;
use Modules\Crud\CrudIndexManager;
use Modules\Crud\CrudSchemaManager;

class ApiKeyController
{
    public function index(Request $request, CrudIndexManager $index, CrudSchemaManager $schema): Response
    {
        $definition = ApiKey::makeCrudDefinition();

        $sort = $request->string('sort')->toString() ?: null;
        $direction = $request->string('direction', 'desc')->toString();
        $search = $request->string('search')->toString() ?: null;
        $filters = $request->array('filters');

        /** @var LengthAwarePaginator<int, ApiKey>|Paginator<int, ApiKey> $apiKeys */
        $apiKeys = $index->paginate(
            definition: $definition,
            page: $request->integer('page', 1),
            perPage: $request->has('per_page') ? $request->integer('per_page') : null,
            sort: $sort,
            direction: $direction,
            search: $search,
            filters: $filters,
        );

        $apiKeys->through(fn (ApiKey $apiKey): array => [
            ...$this->serialize($apiKey),
            'can' => [
                'show' => Gate::allows('view', $apiKey),
                'update' => false,
                'rotate' => Gate::allows('rotate', $apiKey),
                'delete' => Gate::allows('delete', $apiKey),
            ],
        ]);

        return Inertia::render('api-keys/Index', [
            'crud' => $schema->for($definition, 'api-keys', $sort, $direction, $search, $filters),
            'apiKeys' => $apiKeys,
            'can' => [
                'create' => Gate::allows('create', ApiKey::class),
            ],
        ]);
    }

    public function create(CrudSchemaManager $schema): Response
    {
        Gate::authorize('create', ApiKey::class);

        return Inertia::render('api-keys/Create', [
            'crud' => $schema->for(ApiKey::makeCrudDefinition(), 'api-keys'),
        ]);
    }

    public function store(Request $request, ApiKeys $apiKeys): RedirectResponse
    {
        Gate::authorize('create', ApiKey::class);

        $rules = [];

        foreach (ApiKey::makeCrudDefinition()->fields() as $field) {
            $rules[$field->name()] = $field->validationRules();
        }

        /** @var array{name: string, role_id: int|string, expires_at?: string|null} $validated */
        $validated = $request->validate($rules);

        $newApiKey = $apiKeys->create(
            name: $validated['name'],
            expiresAt: filled($validated['expires_at'] ?? null) ? Carbon::parse($validated['expires_at']) : null,
            owner: $request->user(),
            attributes: ['role_id' => $validated['role_id']],
        );

        $this->flashPlainTextKey($newApiKey, __('API key created.'));

        return to_route('api-keys.index');
    }

    public function show(ApiKey $apiKey, CrudSchemaManager $schema): Response
    {
        Gate::authorize('view', $apiKey);

        return Inertia::render('api-keys/Show', [
            'crud' => $schema->for(ApiKey::makeCrudDefinition(), 'api-keys'),
            'apiKey' => $this->serialize($apiKey),
        ]);
    }

    public function rotate(ApiKey $apiKey, ApiKeys $apiKeys): RedirectResponse
    {
        Gate::authorize('rotate', $apiKey);

        $this->flashPlainTextKey($apiKeys->rotate($apiKey), __('API key rotated. The previous key no longer works.'));

        return to_route('api-keys.index');
    }

    /**
     * Revoke the key. The record is kept so its usage remains auditable.
     */
    public function destroy(ApiKey $apiKey, ApiKeys $apiKeys): RedirectResponse
    {
        Gate::authorize('delete', $apiKey);

        $apiKeys->revoke($apiKey);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API key revoked.')]);

        return to_route('api-keys.index');
    }

    /**
     * Flash the plain-text key for a single render: it is never stored and cannot be shown again.
     */
    private function flashPlainTextKey(NewApiKey $newApiKey, string $message): void
    {
        Inertia::flash([
            'toast' => ['type' => 'success', 'message' => $message],
            'apiKey' => [
                'name' => $newApiKey->apiKey->getAttribute('name'),
                'plain_text_key' => $newApiKey->plainTextKey,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ApiKey $apiKey): array
    {
        return [
            'id' => (string) $apiKey->getKey(),
            'name' => $apiKey->name,
            'prefix' => $apiKey->prefix.'…',
            'role_id' => $apiKey->role_id === null ? null : (string) $apiKey->role_id,
            'status' => $apiKey->status,
            'last_used_at' => $apiKey->last_used_at?->toIso8601String(),
            'expires_at' => $apiKey->expires_at?->toIso8601String(),
            'revoked_at' => $apiKey->revoked_at?->toIso8601String(),
            'created_at' => $apiKey->created_at?->toIso8601String(),
        ];
    }
}
