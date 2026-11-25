<?php

namespace Database\Seeders;

use App\Models\Tarifa;
use Illuminate\Database\Seeder;

/**
 * Tarifas de ejemplo en Bs. Ajústalas al reglamento que usen en el proyecto.
 */
class TarifaSeeder extends Seeder
{
    public function run(): void
    {
        $tarifas = [
            ['moto', 2, 5],
            ['auto', 2, 10],
            ['minibus', 2, 15],
            ['minibus', 3, 25],
            ['bus', 2, 20],
            ['bus', 3, 30],
            ['bus', 4, 40],
            ['camion', 2, 20],
            ['camion', 3, 30],
            ['camion', 4, 40],
            ['camion', 5, 50],
            ['camion', 6, 60],
        ];

        foreach ($tarifas as [$categoria, $ejes, $monto]) {
            Tarifa::updateOrCreate(
                ['categoria' => $categoria, 'ejes' => $ejes],
                ['monto' => $monto],
            );
        }
    }
}
