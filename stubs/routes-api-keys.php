<?php

use App\Http\Controllers\ApiKeys\ApiKeyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::post('api-keys/{apiKey}/rotate', [ApiKeyController::class, 'rotate'])->name('api-keys.rotate');
    Route::resource('api-keys', ApiKeyController::class)
        ->parameters(['api-keys' => 'apiKey'])
        ->only(['index', 'create', 'store', 'show', 'destroy']);
});
