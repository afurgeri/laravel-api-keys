<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('api-keys.storage.connection'))
            ->table((string) config('api-keys.table', 'api_keys'), function (Blueprint $table): void {
                $table->foreignId('role_id')->nullable()->after('abilities')->index();
            });
    }

    public function down(): void
    {
        Schema::connection(config('api-keys.storage.connection'))
            ->table((string) config('api-keys.table', 'api_keys'), function (Blueprint $table): void {
                $table->dropIndex(['role_id']);
                $table->dropColumn('role_id');
            });
    }
};
