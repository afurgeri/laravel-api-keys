<?php

namespace App\Crud;

use App\Models\ApiKey;
use Illuminate\Validation\Rule;
use Modules\Crud\Concerns\AuthorizesViaGate;
use Modules\Crud\Concerns\HasDefaultCrudPresentation;
use Modules\Crud\Contracts\AuthorizesCrudIndex;
use Modules\Crud\Contracts\AuthorizesCrudMutations;
use Modules\Crud\Contracts\HasCrudOperations;
use Modules\Crud\Contracts\HasCrudPresentation;
use Modules\Crud\Contracts\HasDefaultCrudPageSize;
use Modules\Crud\Contracts\HasDefaultCrudSort;
use Modules\Crud\CrudColumn;
use Modules\Crud\CrudDefinition;
use Modules\Crud\CrudField;
use Modules\Crud\CrudOperation;
use Modules\Rbac\RbacModels;

class ApiKeyCrudDefinition implements AuthorizesCrudIndex, AuthorizesCrudMutations, CrudDefinition, HasCrudOperations, HasCrudPresentation, HasDefaultCrudPageSize, HasDefaultCrudSort
{
    use AuthorizesViaGate, HasDefaultCrudPresentation;

    /**
     * @return class-string<ApiKey>
     */
    public function model(): string
    {
        return ApiKey::class;
    }

    /**
     * Keys are never edited: rotate them to replace the secret or revoke them
     * through the delete operation, which keeps the record for auditing.
     *
     * @return list<CrudOperation>
     */
    public function disabledOperations(): array
    {
        return [CrudOperation::Update, CrudOperation::Export];
    }

    public function title(): string
    {
        return __('API keys');
    }

    public function description(): ?string
    {
        return __('Keys for external integrations. Each key is shown only once, when it is created or rotated.');
    }

    public function emptyLabel(): ?string
    {
        return __('No API keys found.');
    }

    public function columns(): array
    {
        return [
            CrudColumn::make('name')->sortable()->searchable(),
            CrudColumn::make('prefix')->label('Key')->searchable(),
            CrudColumn::make('role_id')->label('Role'),
            CrudColumn::make('status')->computed()->badge([
                ApiKey::STATUS_ACTIVE => 'success',
                ApiKey::STATUS_EXPIRED => 'warning',
                ApiKey::STATUS_REVOKED => 'neutral',
            ])->labels([
                ApiKey::STATUS_ACTIVE => 'Active',
                ApiKey::STATUS_EXPIRED => 'Expired',
                ApiKey::STATUS_REVOKED => 'Revoked',
            ]),
            CrudColumn::make('last_used_at')->label('Last used')->sortable()->datetime(),
            CrudColumn::make('expires_at')->label('Expires')->sortable()->datetime(),
            CrudColumn::make('created_at')->sortable()->datetime(),
        ];
    }

    public function fields(): array
    {
        return [
            CrudField::make('name', ['required', 'string', 'max:255']),
            CrudField::make('role_id', ['required', Rule::exists(RbacModels::role(), 'id')])
                ->label('Role')
                ->select(RbacModels::role()::query()->orderBy('name')->pluck('name', 'id')->all()),
            CrudField::make('expires_at', ['nullable', 'date', 'after:now'])->label('Expires')->datetime(),
        ];
    }

    public function defaultSortColumn(): string
    {
        return 'created_at';
    }

    public function defaultSortDirection(): string
    {
        return 'desc';
    }

    public function defaultPageSize(): int
    {
        return 10;
    }
}
