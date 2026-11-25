<?php

use App\Http\Middleware\AutenticarApi;
use App\Http\Middleware\EnsureRol;
use App\Http\Middleware\EnsureUsuarioAutorizado;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'broadcasting', 'middleware' => ['web', 'auth']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.acceso' => AutenticarApi::class,
            'autorizado' => EnsureUsuarioAutorizado::class,
            'rol' => EnsureRol::class,
        ]);

        // Autenticar antes de resolver {caseta}, {placa}...: sin credenciales es 401, no 404.
        $middleware->prependToPriorityList(SubstituteBindings::class, AutenticarApi::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
