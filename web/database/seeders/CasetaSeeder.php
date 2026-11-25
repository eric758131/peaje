<?php

namespace Database\Seeders;

use App\Models\Caseta;
use Illuminate\Database\Seeder;

class CasetaSeeder extends Seeder
{
    public function run(): void
    {
        $casetas = [
            ['Caseta Norte', 'Ingreso norte'],
            ['Caseta Centro', 'Tramo central'],
            ['Caseta Sur', 'Salida sur'],
        ];

        foreach ($casetas as [$nombre, $ubicacion]) {
            Caseta::updateOrCreate(['nombre' => $nombre], ['ubicacion' => $ubicacion, 'activa' => true]);
        }
    }
}
