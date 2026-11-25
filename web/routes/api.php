<?php

use App\Http\Controllers\Api\V1\AgenteController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CasetaController;
use App\Http\Controllers\Api\V1\InferenciaController;
use App\Http\Controllers\Api\V1\PasoController;
use App\Http\Controllers\Api\V1\PlacaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
| Todas las rutas exigen la cabecera X-Api-Key (app móvil) o un token Bearer
| de /auth/google o /auth/login, excepto esas dos.
| Documentación: /docs/api (Scramble, generada desde el código).
*/

Route::prefix('v1')->group(function () {

    Route::post('auth/google', [AuthController::class, 'google'])
        ->middleware('throttle:10,1')
        ->name('api.auth.google');

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('api.auth.login');

    Route::middleware('api.acceso')->group(function () {

        Route::post('pasos/frame', [PasoController::class, 'frame'])->name('api.pasos.frame');

        Route::post('placas/consultar', [PlacaController::class, 'consultar'])->name('api.placas.consultar');
        Route::post('placas/consultar-imagen', [PlacaController::class, 'consultarImagen'])->name('api.placas.consultar-imagen');
        Route::get('placas/{placa}/historial', [PlacaController::class, 'historial'])->name('api.placas.historial');

        Route::get('casetas', [CasetaController::class, 'index'])->name('api.casetas.index');
        Route::get('casetas/{caseta}/resumen', [CasetaController::class, 'resumen'])->name('api.casetas.resumen');

        Route::post('agente/chat', [AgenteController::class, 'chat'])->name('api.agente.chat');

        Route::get('inferencia/estado', InferenciaController::class)->name('api.inferencia.estado');
    });
});
