<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Paso;
use App\Services\Agente\ConsultaImagen;
use App\Services\Agente\EstadoPlaca;
use App\Services\Inferencia\InferenciaException;
use App\Support\Placa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlacaController extends Controller
{
    /**
     * Consulta una placa escrita y devuelve su estado.
     */
    public function consultar(Request $request, EstadoPlaca $estados): JsonResponse
    {
        $datos = $request->validate(['placa' => ['required', 'string', 'max:20']]);

        return response()->json($estados->consultar($datos['placa']));
    }

    /**
     * Consulta una placa a partir de una imagen (M1 + OCR) y devuelve su estado.
     */
    public function consultarImagen(Request $request, ConsultaImagen $consulta): JsonResponse
    {
        $datos = $request->validate([
            'imagen' => ['required', 'file', 'image', 'max:12288'],
        ]);

        try {
            $resultado = $consulta->consultar($request->file('imagen')->getRealPath());
        } catch (InferenciaException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json($resultado, $resultado['estado'] === 'sin_placa' ? 422 : 200);
    }

    /**
     * Historial de pasos de una placa, del más reciente al más antiguo.
     */
    public function historial(string $placa): JsonResponse
    {
        $normalizada = Placa::normalizar($placa);

        $pasos = Paso::with('caseta:id,nombre')
            ->where('placa_normalizada', $normalizada)
            ->latest('created_at')
            ->paginate(50);

        return response()->json($pasos);
    }
}
