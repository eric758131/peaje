<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Inferencia\Inferencia;
use Illuminate\Http\JsonResponse;

class InferenciaController extends Controller
{
    /**
     * Indica si la inferencia está disponible (local en PHP o endpoint externo).
     */
    public function __invoke(Inferencia $inferencia): JsonResponse
    {
        $estado = $inferencia->estaDisponible();
        unset($estado['diagnostico']);

        return response()->json($estado);
    }
}
