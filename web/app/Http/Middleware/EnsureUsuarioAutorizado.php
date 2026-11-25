<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solo pasan cuentas activas de la tabla usuarios (una cuenta desactivada pierde el acceso aunque tenga sesión).
 * Para la web redirige a la página de acceso denegado; para la API responde 403.
 */
class EnsureUsuarioAutorizado
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && $usuario->activo) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Acceso denegado: cuenta no autorizada.'], 403);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('acceso.denegado');
    }
}
