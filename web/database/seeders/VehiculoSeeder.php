<?php

namespace Database\Seeders;

use App\Models\Vehiculo;
use Illuminate\Database\Seeder;

/**
 * Los vehículos del sistema: 20 capturas propias del equipo (24/11/2026). El lab pide mínimo 20.
 *
 * - Placa real verificada a ojo en cada foto; categoría según el vehículo.
 * - Saldos variados para probar estados (sin saldo: 05, 11 y 17).
 * - Foto: la imagen YA INFERIDA (placa, máscara y ruedas; personas cubiertas) generada con el
 *   endpoint de Colab. Está en database/seeders/fotos-vehiculos/ y se copia al disco público.
 *
 * Los 27 vehículos de ejemplo de antes están en VehiculoEjemploSeeder (no se ejecuta por defecto).
 */
class VehiculoSeeder extends Seeder
{
    public function run(): void
    {
        $vehiculos = [
            ['3983BZL', 'Captura propia 01', 'auto', 2, 100],
            ['3140PXL', 'Captura propia 02', 'auto', 2, 100],
            ['216TNL', 'Captura propia 03', 'auto', 2, 100],
            ['3614IRL', 'Captura propia 04', 'auto', 2, 100],
            ['5262PRU', 'Captura propia 05', 'auto', 2, 0],
            ['2591NRD', 'Captura propia 06', 'auto', 2, 80],
            ['3863BPG', 'Captura propia 07', 'auto', 2, 150],
            ['3130NHI', 'Captura propia 08', 'auto', 2, 60],
            ['674RFE', 'Captura propia 09', 'auto', 2, 40],
            ['2097DRD', 'Captura propia 10', 'auto', 2, 25],
            ['3468BKB', 'Captura propia 11', 'auto', 2, 0],
            ['4444KRS', 'Captura propia 12', 'minibus', 2, 200],
            ['4739PZR', 'Captura propia 13', 'auto', 2, 90],
            ['4286GZU', 'Captura propia 14', 'auto', 2, 120],
            ['5286BLE', 'Captura propia 15', 'auto', 2, 70],
            ['4776FKL', 'Captura propia 16', 'auto', 2, 30],
            ['4064RIK', 'Captura propia 17', 'auto', 2, 0],
            ['6669RCI', 'Captura propia 18', 'auto', 2, 15],
            ['3041RZC', 'Captura propia 19', 'camion', 2, 500],
            ['3794SBX', 'Captura propia 20', 'minibus', 2, 150],
        ];

        foreach ($vehiculos as [$placa, $propietario, $categoria, $ejes, $saldo]) {
            $vehiculo = Vehiculo::updateOrCreate(
                ['placa' => $placa],
                ['propietario' => $propietario, 'categoria' => $categoria, 'ejes' => $ejes, 'saldo' => $saldo, 'activo' => true],
            );

            $foto = __DIR__.'/fotos-vehiculos/'.$placa.'.jpg';
            if (is_file($foto)) {
                $vehiculo->guardarFoto((string) file_get_contents($foto));
            }
        }
    }
}
