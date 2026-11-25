<?php

namespace App\Providers;

use App\Services\Inferencia\ConexionColab;
use App\Services\Inferencia\Inferencia;
use App\Services\Inferencia\InferenciaHttp;
use App\Services\Inferencia\InferenciaLocal;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // local (modelos ONNX en PHP) o http (Colab / RunPod). Lo guardado desde la web (Estado de la IA)
        // tiene prioridad sobre INFERENCE_DRIVER / INFERENCE_URL / INFERENCE_TOKEN del .env.
        $this->app->bind(Inferencia::class, fn () => ConexionColab::driver() === 'http'
            ? new InferenciaHttp(ConexionColab::url(), ConexionColab::token())
            : new InferenciaLocal(config('peaje.inferencia.local'), (int) config('peaje.inferencia.timeout')));
    }

    public function boot(): void
    {
        // URLs en español: /casetas/crear, /casetas/{id}/editar
        Route::resourceVerbs(['create' => 'crear', 'edit' => 'editar']);

        // La documentación de la API es pública para que el docente la revise.
        // Para restringirla, cambia esta regla por una comprobación del usuario.
        Gate::define('viewScramble', fn () => true);

        // Dos formas de autenticarse (cualquiera de las dos): clave de la app móvil o token de /auth/google (o /auth/login).
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::apiKey('header', 'X-Api-Key')->as('appMovil')
                ->setDescription('Clave de la app móvil (MOVIL_API_KEY).'));
            $openApi->secure(SecurityScheme::http('bearer')->as('token')
                ->setDescription('Token de POST /api/v1/auth/google (ID token de Google) o /api/v1/auth/login.'));
        });
    }
}
