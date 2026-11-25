<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Services\Inferencia\Inferencia;
use App\Services\Inferencia\InferenciaException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Página Estado y prueba de la inferencia sin registrar pasos
 * (la usan también el formulario de vehículos para autocompletar).
 */
class InferenciaController extends Controller
{
    public function estado(Inferencia $inferencia): View
    {
        return view('estado', ['estado' => $inferencia->estaDisponible(), 'conexion' => $this->conexion()]);
    }

    /**
     * Link y token del endpoint de Colab (cambian cada vez que se reinicia el notebook).
     * Se guardan en la base de datos para no tener que editar el .env del servidor.
     */
    public function guardarConexion(Request $request): RedirectResponse
    {
        $request->merge(['url' => trim((string) $request->input('url')), 'token' => trim((string) $request->input('token'))]);

        $datos = $request->validate([
            'driver' => ['required', Rule::in(['http', 'local'])],
            'url' => ['nullable', 'required_if:driver,http', 'url:http,https', 'max:500'],
            'token' => ['nullable', 'string', 'max:255'],
        ], [
            'url.required_if' => 'Pega el link que muestra la celda 10 de Colab.',
            'url.url' => 'El link no es válido (debe empezar con https://).',
        ]);

        Ajuste::guardar(Ajuste::INFERENCIA_DRIVER, $datos['driver']);
        if (filled($datos['url'])) {
            Ajuste::guardar(Ajuste::INFERENCIA_URL, rtrim($datos['url'], '/'));
        }
        // Token vacío = se mantiene el guardado (el campo nunca muestra el token actual).
        if (filled($datos['token'])) {
            Ajuste::guardar(Ajuste::INFERENCIA_TOKEN, $datos['token']);
        }

        $estado = app(Inferencia::class)->estaDisponible();

        return redirect()->route('estado')->with(
            $estado['disponible'] ? 'ok' : 'error',
            $estado['disponible'] ? 'Conexión guardada. La inferencia responde correctamente.' : 'Conexión guardada, pero no responde: '.$estado['detalle'],
        );
    }

    /** Borra lo guardado desde la web: se vuelve a usar INFERENCE_DRIVER / INFERENCE_URL / INFERENCE_TOKEN del .env. */
    public function restablecerConexion(): RedirectResponse
    {
        foreach ([Ajuste::INFERENCIA_DRIVER, Ajuste::INFERENCIA_URL, Ajuste::INFERENCIA_TOKEN] as $clave) {
            Ajuste::guardar($clave, null);
        }

        return redirect()->route('estado')->with('ok', 'Se usa otra vez la conexión del archivo .env.');
    }

    /** @return array{driver: string, url: ?string, token: bool, desde_web: bool} */
    private function conexion(): array
    {
        return [
            'driver' => (string) Ajuste::valor(Ajuste::INFERENCIA_DRIVER, config('peaje.inferencia.driver')),
            'url' => Ajuste::valor(Ajuste::INFERENCIA_URL, config('peaje.inferencia.url')),
            'token' => filled(Ajuste::valor(Ajuste::INFERENCIA_TOKEN, config('peaje.inferencia.token'))),
            'desde_web' => Ajuste::tiene(Ajuste::INFERENCIA_DRIVER) || Ajuste::tiene(Ajuste::INFERENCIA_URL) || Ajuste::tiene(Ajuste::INFERENCIA_TOKEN),
        ];
    }

    public function probar(Request $request, Inferencia $inferencia): JsonResponse
    {
        $request->validate(['imagen' => ['required', 'file', 'image', 'max:12288']]);

        try {
            $lectura = $inferencia->inferir($request->file('imagen')->getRealPath());
        } catch (InferenciaException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $evidencia = $lectura['evidencia'] ?? null;
        unset($lectura['evidencia']);

        return response()->json([
            'lectura' => $lectura,
            'evidencia' => $evidencia ? 'data:image/jpeg;base64,'.$evidencia : null,
        ]);
    }
}
