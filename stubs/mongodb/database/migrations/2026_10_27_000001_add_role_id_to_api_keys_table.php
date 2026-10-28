<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('api-keys.storage.connection'))
            ->table((string) config('api-keys.table', 'api_keys'), function (Blueprint $collection): void {
                $collection->index('role_id');
            });
    }

    public function down(): void
    {
        Schema::connection(config('api-keys.storage.connection'))
            ->table((string) config('api-keys.table', 'api_keys'), function (Blueprint $collection): void {
                $collection->dropIndex(['role_id']);
            });
    }
};
