<?php

namespace App\Services\Peaje;

use App\Models\Alerta;
use App\Models\Paso;
use Illuminate\Support\Carbon;

/**
 * Contadores del día: pasos, recaudación y alertas pendientes.
 * Se usa en el panel y se envía junto con cada evento en tiempo real.
 */
class Resumen
{
    /**
     * @return array{pasos: int, recaudado: float, alertas_pendientes: int, fecha: string}
     */
    public function delDia(?int $casetaId = null): array
    {
        $hoy = Carbon::today();

        $pasos = Paso::query()
            ->whereDate('created_at', $hoy)
            ->when($casetaId, fn ($q) => $q->where('caseta_id', $casetaId));

        return [
            'pasos' => (clone $pasos)->count(),
            'recaudado' => (float) (clone $pasos)->where('estado', Paso::COBRADO)->sum('monto'),
            'alertas_pendientes' => Alerta::where('atendida', false)->count(),
            'fecha' => $hoy->toDateString(),
        ];
    }
}
