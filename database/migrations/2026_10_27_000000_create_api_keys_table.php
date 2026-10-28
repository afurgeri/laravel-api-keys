<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('api-keys.storage.connection'));
        $table = (string) config('api-keys.table', 'api_keys');

        if (config('api-keys.storage.driver') === 'mongodb') {
            $schema->create($table, function (MongoDB\Laravel\Schema\Blueprint $collection): void {
                $collection->unique('key_hash');
                $collection->index(['owner_type', 'owner_id']);
            });

            return;
        }

        $schema->create($table, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('prefix', 32);
            $table->char('key_hash', 64)->unique();
            $table->json('abilities')->nullable();
            $table->nullableMorphs('owner');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection(config('api-keys.storage.connection'))
            ->dropIfExists((string) config('api-keys.table', 'api_keys'));
    }
};
