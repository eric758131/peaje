<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Caseta;
use App\Models\Paso;
use App\Models\Usuario;
use App\Services\Inferencia\InferenciaException;
use App\Services\Peaje\ProcesadorFrame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Caseta desde el navegador: foto, video o cámara.
 * El navegador extrae los frames del video y envía cada uno; todos pasan por la inferencia.
 */
class RegistroController extends Controller
{
    public function index(): View
    {
        return view('registro', ['casetas' => Caseta::where('activa', true)->orderBy('nombre')->get()]);
    }

    public function frame(Request $request, ProcesadorFrame $procesador): JsonResponse
    {
        $datos = $request->validate([
            'imagen' => ['required', 'file', 'image', 'max:12288'],
            'caseta_id' => ['required', 'integer', Rule::exists('casetas', 'id')->where('activa', true)],
        ]);

        /** @var Usuario $operador */
        $operador = $request->user();

        try {
            $r = $procesador->procesar($request->file('imagen'), Caseta::findOrFail($datos['caseta_id']), $operador);
        } catch (InferenciaException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json([
            'resultado' => $r['resultado'],
            'mensaje' => ProcesadorFrame::mensaje($r),
            'lectura' => $r['lectura'],
            'paso' => self::paso($r['paso']),
            // Todos los vehículos con placa del frame (el principal incluido).
            'pasos' => array_map(fn ($p) => [
                'resultado' => $p['resultado'],
                'placa' => $p['paso']?->placa_normalizada ?? $p['lectura']['placa'] ?? null,
                'paso' => self::paso($p['paso']),
            ], $r['pasos'] ?? []),
            'evidencia' => $r['evidencia'] ? 'data:image/jpeg;base64,'.$r['evidencia'] : null,
        ]);
    }

    /** @return array<string, mixed>|null */
    private static function paso(?Paso $paso): ?array
    {
        if (! $paso) {
            return null;
        }

        $paso->loadMissing(['caseta', 'vehiculo']);

        return $paso->aArregloApi() + [
            'caseta' => $paso->caseta?->nombre,
            'propietario' => $paso->vehiculo?->propietario,
            'saldo' => $paso->vehiculo ? (float) $paso->vehiculo->fresh()->saldo : null,
            'url' => route('pasos.show', $paso),
        ];
    }
}
