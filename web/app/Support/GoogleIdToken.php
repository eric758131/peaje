<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Valida un ID token de Google (el que entrega Google Sign-In en la app o en la web).
 * Google lo comprueba (firma y vencimiento) en su endpoint tokeninfo; aquí se revisa además que fue
 * emitido para uno de nuestros Client ID y que el correo está verificado.
 */
class GoogleIdToken
{
    private const URL = 'https://oauth2.googleapis.com/tokeninfo';

    /**
     * @return array{email: string, nombre: ?string}|null null si el token no es válido.
     */
    public static function validar(string $idToken): ?array
    {
        $clientes = array_filter([config('services.google.client_id'), ...config('services.google.client_ids_extra', [])]);

        if ($clientes === []) {
            return null;
        }

        try {
            $respuesta = Http::timeout(10)->get(self::URL, ['id_token' => $idToken]);
        } catch (ConnectionException) {
            return null;
        }

        $datos = $respuesta->json();

        if (! $respuesta->successful() || ! is_array($datos)) {
            return null;
        }

        $emisorOk = in_array($datos['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true);
        $audienciaOk = in_array($datos['aud'] ?? '', $clientes, true);
        $vigente = (int) ($datos['exp'] ?? 0) > time();
        $verificado = in_array($datos['email_verified'] ?? false, [true, 'true'], true);

        if (! $emisorOk || ! $audienciaOk || ! $vigente || ! $verificado || blank($datos['email'] ?? null)) {
            return null;
        }

        return ['email' => mb_strtolower(trim($datos['email'])), 'nombre' => $datos['name'] ?? null];
    }
}
