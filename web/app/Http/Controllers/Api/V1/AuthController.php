<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Support\GoogleIdToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Recibe el ID token de Google (Google Sign-In) y devuelve un token de sesión propio (Bearer).
     *
     * Solo entran las cuentas activas de la tabla usuarios (lista blanca); las demás reciben 403.
     *
     * @unauthenticated
     *
     * @response array{token: string, usuario: array{id: int, nombre: string, email: string, rol: string}}
     */
    public function google(Request $request): JsonResponse
    {
        $datos = $request->validate(['id_token' => ['required', 'string', 'max:4096']]);

        $cuenta = GoogleIdToken::validar($datos['id_token']);

        if (! $cuenta) {
            return response()->json(['message' => 'El token de Google no es válido.'], 401);
        }

        $usuario = Usuario::where('email', $cuenta['email'])->first();

        if (! $usuario || ! $usuario->activo) {
            return response()->json(['message' => 'Acceso denegado: la cuenta '.$cuenta['email'].' no está autorizada.'], 403);
        }

        return $this->conToken($usuario);
    }

    /**
     * Recibe email y contraseña y devuelve un token de sesión (Bearer) para la API.
     *
     * La app móvil no lo usa: se identifica con la cabecera X-Api-Key.
     * Sirve para clientes como Postman o la documentación de la API.
     *
     * @unauthenticated
     *
     * @response array{token: string, usuario: array{id: int, nombre: string, email: string, rol: string}}
     */
    public function login(Request $request): JsonResponse
    {
        abort_unless(config('peaje.login_con_contrasena'), 404);

        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $usuario = Usuario::where('email', mb_strtolower(trim($datos['email'])))->first();

        if (! $usuario || ! $usuario->password || ! Hash::check($datos['password'], $usuario->password)) {
            return response()->json(['message' => 'El correo o la contraseña no son correctos.'], 401);
        }

        if (! $usuario->activo) {
            return response()->json(['message' => 'Acceso denegado: cuenta desactivada.'], 403);
        }

        return $this->conToken($usuario);
    }

    private function conToken(Usuario $usuario): JsonResponse
    {
        return response()->json([
            'token' => $usuario->createToken('api')->plainTextToken,
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'email' => $usuario->email,
                'rol' => $usuario->rol,
            ],
        ]);
    }
}
