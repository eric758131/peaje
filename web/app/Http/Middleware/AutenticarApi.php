<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Acceso a la API por una de dos vías:
 *
 * 1. Cabecera X-Api-Key igual a MOVIL_API_KEY: la app móvil (no tiene login).
 *    El paso se registra sin operador (usuario_id = null).
 * 2. Token Bearer de POST /api/v1/auth/google (o /auth/login), de una cuenta activa.
 */
class AutenticarApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $clave = (string) config('peaje.api_key');
        $enviada = (string) $request->header('X-Api-Key');

        if ($clave !== '' && $enviada !== '' && hash_equals($clave, $enviada)) {
            return $next($request);
        }

        $usuario = Auth::guard('sanctum')->user();

        if (! $usuario) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        if (! $usuario->activo) {
            return response()->json(['message' => 'Acceso denegado: cuenta desactivada.'], 403);
        }

        Auth::shouldUse('sanctum');

        return $next($request);
    }
}
