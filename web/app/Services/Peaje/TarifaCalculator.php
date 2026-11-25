<?php

namespace App\Services\Peaje;

use App\Models\Tarifa;

/**
 * Calcula el monto según categoría y número de ejes.
 */
class TarifaCalculator
{
    /**
     * Busca la tarifa exacta; si no existe, usa la de la misma categoría con
     * más ejes más cercana. Devuelve 0 si la categoría no tiene tarifas.
     */
    public function montoPara(string $categoria, int $ejes): float
    {
        $exacta = Tarifa::where('categoria', $categoria)->where('ejes', $ejes)->value('monto');

        if ($exacta !== null) {
            return (float) $exacta;
        }

        $cercana = Tarifa::where('categoria', $categoria)
            ->orderByRaw('ABS(ejes - ?)', [$ejes])
            ->first();

        return $cercana ? (float) $cercana->monto : 0.0;
    }
}
