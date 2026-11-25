<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Caseta;
use App\Models\Usuario;
use App\Services\Inferencia\InferenciaException;
use App\Services\Peaje\ProcesadorFrame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PasoController extends Controller
{
    /**
     * Recibe un frame de la app de caseta, ejecuta la inferencia, registra el paso y lo emite en tiempo real.
     *
     * Si la misma placa ya pasó por esa caseta hace menos de VENTANA_DUPLICADO segundos,
     * responde 200 con `duplicado: true` y no cobra de nuevo.
     *
     * Si el frame trae varios vehículos con placa, se registra un paso por cada uno (`pasos`).
     *
     * @response 201 array{paso: array<string, mixed>, lectura: array<string, mixed>, duplicado: bool, pasos: list<array<string, mixed>>}
     * @response 422 array{estado: string, message: string}
     */
    public function frame(Request $request, ProcesadorFrame $procesador): JsonResponse
    {
        $datos = $request->validate([
            'imagen' => ['required', 'file', 'image', 'max:12288'],
            'caseta_id' => ['required', 'integer', Rule::exists('casetas', 'id')->where('activa', true)],
        ]);

        // Con X-Api-Key (app móvil) no hay usuario: el paso queda sin operador.
        /** @var Usuario|null $operador */
        $operador = $request->user();

        try {
            $resultado = $procesador->procesar($request->file('imagen'), Caseta::findOrFail($datos['caseta_id']), $operador);
        } catch (InferenciaException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        if ($resultado['resultado'] === ProcesadorFrame::SIN_PLACA) {
            return response()->json([
                'estado' => 'sin_placa',
                'message' => ProcesadorFrame::mensaje($resultado),
                'lectura' => self::lectura($resultado['lectura']),
            ], 422);
        }

        $duplicado = $resultado['resultado'] === ProcesadorFrame::DUPLICADO;

        return response()->json([
            'paso' => $resultado['paso']->aArregloApi(),
            'lectura' => self::lectura($resultado['lectura']),
            'duplicado' => $duplicado,
            // Todos los vehículos con placa del frame (el principal incluido).
            'pasos' => array_values(array_filter(array_map(fn ($p) => $p['paso'] ? $p['paso']->aArregloApi() + [
                'duplicado' => $p['resultado'] === ProcesadorFrame::DUPLICADO,
            ] : null, $resultado['pasos'] ?? []))),
        ], $duplicado ? 200 : 201);
    }

    /**
     * @param  array<string, mixed>  $lectura
     * @return array<string, mixed>
     */
    private static function lectura(array $lectura): array
    {
        return [
            'placa' => $lectura['placa'] ?? null,
            'confianza' => $lectura['confianza'] ?? null,
            'categoria' => $lectura['categoria'] ?? null,
            'ejes' => $lectura['ejes'] ?? null,
            'pasajeros' => $lectura['pasajeros'] ?? null,
            'latencia_ms' => $lectura['latencia_ms'] ?? null,
        ];
    }
}
