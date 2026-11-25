<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: ->middleware('rol:administrador'). Varios roles separados por coma.
 */
class EnsureRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();

        if ($usuario instanceof Usuario && in_array($usuario->rol, $roles, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'No tienes permisos para esta acción.'], 403);
        }

        abort(403, 'No tienes permisos para esta acción.');
    }
}
