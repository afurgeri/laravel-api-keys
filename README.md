# Laravel API Keys

`afurgeri/laravel-api-keys` emite API keys para integraciones externas y autentica peticiones con ellas. Las claves se guardan como hash, se muestran una sola vez y se pueden renovar o revocar. Funciona con SQL o MongoDB e incluye pantallas administrativas instalables basadas en Laravel CRUD, Inertia/Vue y Laravel RBAC.

## Requisitos

- PHP `^8.3` y Laravel 13.
- Una conexión SQL de Laravel o MongoDB.
- Para MongoDB: extensión `ext-mongodb` y `mongodb/laravel-mongodb:^5.11`.
- Para las pantallas: `afurgeri/laravel-crud:^0.9.15`, `afurgeri/laravel-rbac:^0.6` e `inertiajs/inertia-laravel:^3.0`. El guard y el servicio no las necesitan.

## Instalación

```bash
composer require afurgeri/laravel-api-keys
```

Mientras se desarrolla localmente, registrar un repositorio `path` en el `composer.json` del proyecto:

```json
{
    "type": "path",
    "url": "modules/ApiKeys",
    "options": {
        "symlink": true,
        "versions": { "afurgeri/laravel-api-keys": "0.1.0" }
    }
}
```

El service provider se registra automáticamente. **Elegir el almacenamiento antes de migrar**; la migración crea `api_keys`:

```bash
php artisan migrate --no-interaction
```

## Configuración

El paquete funciona con valores por defecto. Para cambiarlos, publicar la configuración:

```bash
php artisan vendor:publish --tag=api-keys-config --no-interaction
```

| Opción | Por defecto | Uso |
| --- | --- | --- |
| `storage.driver` | `env('API_KEYS_STORAGE', 'sql')` | `sql` o `mongodb` |
| `storage.connection` | `env('API_KEYS_CONNECTION')` | Conexión de `config/database.php`; vacío usa la predeterminada |
| `model` | `null` | Modelo propio que extienda el del paquete; `null` usa el del driver |
| `table` | `api_keys` | Tabla o colección |
| `guard` | `api-key` | Nombre del guard registrado |
| `prefix` | `env('API_KEYS_PREFIX', 'ak')` | Prefijo visible: `ak_…` |
| `length` | `40` | Longitud de la parte aleatoria (mínimo 32) |
| `bearer` | `true` | Aceptar `Authorization: Bearer <key>` |
| `headers` | `['X-Api-Key']` | Cabeceras aceptadas, en orden |
| `last_used_interval` | `60` | Segundos mínimos entre escrituras de `last_used_at` |
| `rate_limit` | `env('API_KEYS_RATE_LIMIT', 60)` | Peticiones por minuto y por key del limitador `api-keys` |

### MongoDB

```dotenv
API_KEYS_STORAGE=mongodb
API_KEYS_CONNECTION=mongodb
```

El driver y la conexión deben coincidir; si no, se lanza `InvalidArgumentException`. Cambiar de motor no traslada las keys existentes.

## Uso del servicio

```php
use Modules\ApiKeys\ApiKeys;

$keys = app(ApiKeys::class);

$nueva = $keys->create(
    name: 'Integración ERP',
    abilities: ['clients.view'],
    expiresAt: now()->addYear(),
    owner: $request->user(),
);

$nueva->plainTextKey; // mostrar ahora: no se guarda y no se puede recuperar
$nueva->apiKey;       // modelo guardado

$renovada = $keys->rotate($nueva->apiKey); // la clave anterior deja de funcionar
$keys->revoke($nueva->apiKey);             // conserva el registro con revoked_at
```

Solo se guardan el hash SHA-256 y un prefijo corto para identificar la key en pantalla. `rotate()` conserva el registro, el nombre y los permisos.

## Proteger rutas

```php
Route::middleware(['auth:api-key', 'throttle:api-keys'])->prefix('api/v1')->group(function () {
    Route::get('clients', ClientController::class)->middleware('api-key.ability:clients.view');
});
```

- `auth:api-key` autentica con `X-Api-Key: <key>` o `Authorization: Bearer <key>`. Una key ausente, desconocida, revocada o vencida responde `401` cuando la petición espera JSON.
- `api-key.ability:a,b` exige **todas** las abilities listadas; si falta alguna responde `403`. `*` concede todas.
- `throttle:api-keys` limita por key, o por IP si la petición no está autenticada.
- `$request->user()` devuelve el modelo de la key, que implementa `Modules\ApiKeys\Contracts\ApiKey`.

## Pantallas administrativas y RBAC

`api-keys:install` copia en la aplicación el modelo, la policy, la definición CRUD, el controller, las rutas y las páginas Vue. Si falta el frontend genérico de CRUD, ejecuta `crud:install` antes.

```bash
php artisan api-keys:install --no-interaction            # usa api-keys.storage.driver
php artisan api-keys:install --storage=mongodb --no-interaction
php artisan migrate --no-interaction
php artisan db:seed --class='Modules\ApiKeys\Database\Seeders\ApiKeyPermissionSeeder' --no-interaction
php artisan wayfinder:generate --with-form --no-interaction
npm run build
```

El instalador:

- Publica `config/api-keys.php` con `'model' => App\Models\ApiKey::class`.
- Crea `App\Models\ApiKey`, que asigna un **rol de RBAC** a cada key. La key implementa `HasPermissions`, así que funcionan el middleware `permission:<nombre>` y las policies existentes.
- Agrega una migración para `role_id` y `require __DIR__.'/api-keys.php';` en `routes/web.php`.
- No sobrescribe archivos existentes salvo con `--force`.

Con RBAC, proteger la API con los permisos del rol:

```php
Route::middleware(['auth:api-key', 'throttle:api-keys', 'permission:clients.view'])
    ->get('api/v1/clients', ClientController::class);
```

Rutas de administración:

| Ruta nombrada | Función |
| --- | --- |
| `api-keys.index` | Listado con estado, rol, último uso y vencimiento |
| `api-keys.create` / `api-keys.store` | Crear una key; se muestra una vez en un diálogo |
| `api-keys.show` | Detalle de la key, sin el secreto |
| `api-keys.rotate` | Renovar; se muestra la nueva key una vez |
| `api-keys.destroy` | Revocar; el registro se conserva |

Permisos que crea el seeder (no los asigna a ningún rol):

- `api-keys.view`: listar y ver keys.
- `api-keys.create`: crear keys.
- `api-keys.rotate`: renovar keys activas.
- `api-keys.revoke`: revocar keys activas.

Una petición autenticada con una API key nunca puede administrar keys, aunque su rol tenga esos permisos.

### Seguridad

- La clave en texto plano viaja una sola vez como flash data de Inertia: queda en la sesión hasta la siguiente respuesta y no se guarda en el historial del navegador.
- Asignar a las keys roles con permisos mínimos. No usar el rol protegido de administración.
- Servir la API solo por HTTPS: la key es una credencial bearer.

## Pruebas del paquete

```bash
composer install
composer test
MONGODB_TEST_URI=mongodb://127.0.0.1:27017 composer test
```

Las pruebas SQL usan SQLite en memoria. Las de MongoDB se omiten sin `MONGODB_TEST_URI`; crean una base `api_keys_test_*` aleatoria y la eliminan al terminar.
