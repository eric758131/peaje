<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Caseta;
use App\Services\Peaje\Resumen;
use Illuminate\Http\JsonResponse;

class CasetaController extends Controller
{
    /**
     * Casetas activas, para que la app elija en cuál está.
     */
    public function index(): JsonResponse
    {
        return response()->json(
            Caseta::where('activa', true)->orderBy('nombre')->get(['id', 'nombre', 'ubicacion'])
        );
    }

    /**
     * Conteo y recaudación del día de una caseta.
     */
    public function resumen(Caseta $caseta, Resumen $resumen): JsonResponse
    {
        return response()->json([
            'caseta' => ['id' => $caseta->id, 'nombre' => $caseta->nombre],
            'resumen' => $resumen->delDia($caseta->id),
        ]);
    }
}
