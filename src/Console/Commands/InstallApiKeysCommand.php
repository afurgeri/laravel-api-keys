<?php

namespace Modules\ApiKeys\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallApiKeysCommand extends Command
{
    protected $signature = 'api-keys:install
        {--storage= : The storage driver to target (sql or mongodb). Defaults to api-keys.storage.driver}
        {--force : Overwrite existing API key integration files}';

    protected $description = 'Install the API key admin screens, controller, policy and RBAC integration';

    /**
     * @var array<string, string>
     */
    private const FILES = [
        'stubs/app/Crud/ApiKeyCrudDefinition.php' => 'app/Crud/ApiKeyCrudDefinition.php',
        'stubs/app/Policies/ApiKeyPolicy.php' => 'app/Policies/ApiKeyPolicy.php',
        'stubs/app/Http/Controllers/ApiKeys/ApiKeyController.php' => 'app/Http/Controllers/ApiKeys/ApiKeyController.php',
        'stubs/routes-api-keys.php' => 'routes/api-keys.php',
        'stubs/resources/js/pages/api-keys/Index.vue' => 'resources/js/pages/api-keys/Index.vue',
        'stubs/resources/js/pages/api-keys/Create.vue' => 'resources/js/pages/api-keys/Create.vue',
        'stubs/resources/js/pages/api-keys/Show.vue' => 'resources/js/pages/api-keys/Show.vue',
        'stubs/resources/js/pages/api-keys/ApiKeySecretDialog.vue' => 'resources/js/pages/api-keys/ApiKeySecretDialog.vue',
    ];

    /**
     * Files whose content depends on the storage driver.
     *
     * @var array<string, string>
     */
    private const STORAGE_FILES = [
        'app/Models/ApiKey.php' => 'app/Models/ApiKey.php',
        'database/migrations/2026_10_27_000001_add_role_id_to_api_keys_table.php' => 'database/migrations/2026_10_27_000001_add_role_id_to_api_keys_table.php',
    ];

    public function handle(): int
    {
        $storage = (string) ($this->option('storage') ?: config('api-keys.storage.driver', 'sql'));

        if (! in_array($storage, ['sql', 'mongodb'], true)) {
            $this->components->error('The --storage option must be either sql or mongodb.');

            return self::FAILURE;
        }

        if ($storage === 'mongodb' && ! class_exists('MongoDB\\Laravel\\Eloquent\\Model')) {
            $this->components->error('MongoDB support is not installed. Run [composer require mongodb/laravel-mongodb] first.');

            return self::FAILURE;
        }

        foreach ([
            'Modules\\Crud\\CrudIndexManager' => 'afurgeri/laravel-crud',
            'Modules\\Rbac\\Models\\Role' => 'afurgeri/laravel-rbac',
            'Inertia\\Inertia' => 'inertiajs/inertia-laravel',
        ] as $class => $package) {
            if (! class_exists($class)) {
                $this->components->error("The API key screens require {$package}. Run [composer require {$package}] first.");

                return self::FAILURE;
            }
        }

        if (! File::exists(base_path('resources/js/components/crud/CrudPage.vue'))) {
            $this->components->info('Generic CRUD frontend files are missing; installing them.');

            if ($this->call('crud:install', ['--skip-existing' => true]) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        $packageRoot = dirname(__DIR__, 3);
        $targets = [];

        foreach ($this->filesFor($storage) as $source => $target) {
            $targets[] = [
                'source' => $packageRoot.'/'.$source,
                'target' => base_path($target),
            ];
        }

        $conflicts = array_values(array_filter(
            $targets,
            fn (array $file): bool => File::exists($file['target']),
        ));

        if ($conflicts !== [] && ! $this->option('force')) {
            $this->components->error('API key integration files already exist. Use --force to overwrite them:');

            foreach ($conflicts as $file) {
                $this->line('  - '.$file['target']);
            }

            return self::FAILURE;
        }

        foreach ($targets as $file) {
            File::ensureDirectoryExists(dirname($file['target']));
            File::copy($file['source'], $file['target']);
        }

        $this->publishConfig($packageRoot, $storage);
        $this->appendRouteRequire();

        $this->components->info('API key integration installed.');
        $this->newLine();
        $this->line('Next steps:');
        if ($storage === 'mongodb') {
            $this->line('  0. Set API_KEYS_STORAGE=mongodb and API_KEYS_CONNECTION=<your mongodb connection> in .env');
        }
        $this->line('  1. php artisan migrate');
        $this->line("  2. php artisan db:seed --class='Modules\\ApiKeys\\Database\\Seeders\\ApiKeyPermissionSeeder'");
        $this->line('  3. php artisan wayfinder:generate --with-form && npm run build');
        $this->line('  4. Protect your API routes with: auth:api-key, throttle:api-keys and permission:<name>');

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function filesFor(string $storage): array
    {
        $files = [];
        $directory = $storage === 'mongodb' ? 'stubs/mongodb' : 'stubs';

        foreach (self::STORAGE_FILES as $source => $target) {
            $files["{$directory}/{$source}"] = $target;
        }

        return [...$files, ...self::FILES];
    }

    /**
     * Publish the configuration pointing to the application's API key model.
     */
    private function publishConfig(string $packageRoot, string $storage): void
    {
        $path = config_path('api-keys.php');

        if (File::exists($path) && ! $this->option('force')) {
            if (! str_contains(File::get($path), 'App\\Models\\ApiKey')) {
                $this->components->warn("config/api-keys.php already exists; set 'model' => App\\Models\\ApiKey::class manually.");
            }

            return;
        }

        File::put($path, str_replace(
            ["'model' => null,", "env('API_KEYS_STORAGE', 'sql')"],
            ["'model' => App\\Models\\ApiKey::class,", "env('API_KEYS_STORAGE', '{$storage}')"],
            File::get($packageRoot.'/config/api-keys.php'),
        ));
    }

    private function appendRouteRequire(): void
    {
        $path = base_path('routes/web.php');

        if (! File::exists($path)) {
            $this->components->warn("routes/web.php was not found; add require __DIR__.'/api-keys.php'; manually.");

            return;
        }

        $contents = File::get($path);
        $require = "require __DIR__.'/api-keys.php';";

        if (str_contains($contents, $require)) {
            return;
        }

        File::put($path, rtrim($contents)."\n\n{$require}\n");
    }
}
