<?php

use App\Http\Controllers\Web\AlertaController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CasetaController;
use App\Http\Controllers\Web\ChatController;
use App\Http\Controllers\Web\InferenciaController;
use App\Http\Controllers\Web\PanelController;
use App\Http\Controllers\Web\PasoController;
use App\Http\Controllers\Web\RegistroController;
use App\Http\Controllers\Web\TarifaController;
use App\Http\Controllers\Web\UsuarioController;
use App\Http\Controllers\Web\VehiculoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web
|--------------------------------------------------------------------------
| Acceso con email y contraseña. Solo cuentas activas de la tabla usuarios.
*/

Route::redirect('/', '/panel');

// Acceso
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'autenticar'])->name('login.autenticar');
    // Google OAuth 2.0
    Route::get('/auth/google', [AuthController::class, 'google'])->name('login.google');
    Route::get('/auth/google/callback', [AuthController::class, 'googleCallback'])->name('login.google.callback');
});
Route::get('/acceso-denegado', [AuthController::class, 'denegado'])->name('acceso.denegado');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Solo cuentas autorizadas (operadores y administradores)
Route::middleware(['auth', 'autorizado'])->group(function () {

    Route::get('/panel', PanelController::class)->name('panel');

    // Registrar pasos desde el navegador: foto, video o cámara. Todo pasa por la inferencia.
    Route::get('/registrar', [RegistroController::class, 'index'])->name('registro');
    Route::post('/registrar/frame', [RegistroController::class, 'frame'])->name('registro.frame');

    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    Route::post('/chat', [ChatController::class, 'enviar'])->name('chat.enviar');
    Route::post('/chat/reiniciar', [ChatController::class, 'reiniciar'])->name('chat.reiniciar');

    Route::get('/pasos', [PasoController::class, 'index'])->name('pasos.index');
    Route::get('/pasos/{paso}', [PasoController::class, 'show'])->name('pasos.show');

    Route::get('/alertas', [AlertaController::class, 'index'])->name('alertas.index');
    Route::post('/alertas/atender-todas', [AlertaController::class, 'atenderTodas'])->name('alertas.atender-todas');
    Route::post('/alertas/{alerta}/atender', [AlertaController::class, 'atender'])->name('alertas.atender');

    Route::get('/estado', [InferenciaController::class, 'estado'])->name('estado');
    Route::post('/inferencia/probar', [InferenciaController::class, 'probar'])->name('inferencia.probar');

    // Solo administradores gestionan datos
    Route::middleware('rol:administrador')->group(function () {
        Route::get('/vehiculos/crear', [VehiculoController::class, 'create'])->name('vehiculos.create');
        Route::post('/vehiculos', [VehiculoController::class, 'store'])->name('vehiculos.store');
        Route::get('/vehiculos/{vehiculo}/editar', [VehiculoController::class, 'edit'])->name('vehiculos.edit');
        Route::put('/vehiculos/{vehiculo}', [VehiculoController::class, 'update'])->name('vehiculos.update');
        Route::delete('/vehiculos/{vehiculo}', [VehiculoController::class, 'destroy'])->name('vehiculos.destroy');

        Route::resource('casetas', CasetaController::class)->except('show')
            ->parameters(['casetas' => 'caseta'])->names('casetas');
        Route::resource('tarifas', TarifaController::class)->except('show')->names('tarifas');
        Route::resource('usuarios', UsuarioController::class)->except('show')->names('usuarios');

        Route::delete('/pasos/{paso}', [PasoController::class, 'destroy'])->name('pasos.destroy');

        // Link y token de Colab, sin editar el .env
        Route::put('/estado/conexion', [InferenciaController::class, 'guardarConexion'])->name('estado.conexion');
        Route::delete('/estado/conexion', [InferenciaController::class, 'restablecerConexion'])->name('estado.conexion.restablecer');
    });

    // Después de /vehiculos/crear para que "crear" no se tome como un id.
    Route::get('/vehiculos', [VehiculoController::class, 'index'])->name('vehiculos.index');
    Route::get('/vehiculos/{vehiculo}', [VehiculoController::class, 'show'])->name('vehiculos.show');
});
