<?php

namespace Database\Seeders;

use App\Models\Vehiculo;
use Illuminate\Database\Seeder;

/**
 * 27 vehículos de EJEMPLO (placas inventadas). No se ejecuta por defecto: el sistema usa
 * los 20 vehículos de las capturas propias (VehiculoSeeder). Para cargarlos también:
 *   php artisan db:seed --class=VehiculoEjemploSeeder
 */
class VehiculoEjemploSeeder extends Seeder
{
    public function run(): void
    {
        $vehiculos = [
            ['1234ABC', 'Juan Pérez', 'auto', 2, 150],
            ['2345BCD', 'María Quispe', 'auto', 2, 80],
            ['3456CDE', 'Luis Mamani', 'auto', 2, 0],
            ['4567DEF', 'Ana Condori', 'auto', 2, 200],
            ['5678EFG', 'Carlos Flores', 'moto', 2, 50],
            ['6789FGH', 'Lucía Rojas', 'moto', 2, 3],
            ['7890GHI', 'Pedro Vargas', 'moto', 2, 40],
            ['8901HIJ', 'Rosa Choque', 'moto', 2, 25],
            ['9012IJK', 'Miguel Ticona', 'minibus', 2, 300],
            ['1023JKL', 'Sofía Apaza', 'minibus', 3, 120],
            ['1134KLM', 'Jorge Huanca', 'minibus', 2, 0],
            ['1245LMN', 'Elena Mendoza', 'minibus', 3, 250],
            ['1356MNO', 'Ricardo Gutiérrez', 'bus', 2, 500],
            ['1467NOP', 'Patricia Salazar', 'bus', 3, 400],
            ['1578OPQ', 'Hugo Villca', 'bus', 4, 60],
            ['1689PQR', 'Gabriela Soliz', 'bus', 3, 350],
            ['1790QRS', 'Fernando Limachi', 'camion', 2, 900],
            ['1801RST', 'Claudia Navia', 'camion', 3, 700],
            ['1912STU', 'Raúl Quispe', 'camion', 4, 0],
            ['2023TUV', 'Marcela Ayala', 'camion', 5, 450],
            ['2134UVW', 'Óscar Castillo', 'camion', 6, 800],
            ['2245VWX', 'Verónica Torrez', 'auto', 2, 95],
            ['2356WXY', 'Andrés Cárdenas', 'moto', 2, 15],
            ['2467XYZ', 'Daniela Ortiz', 'camion', 3, 600],
            // Vehículos de las fotos y el video de archivos-prueba/ (fotos reales de Bolivia).
            ['4027UGL', 'Transportes Altiplano', 'bus', 2, 100],
            ['2097ZHB', 'Gonzalo Mercado', 'auto', 2, 50],
            ['195SHC', 'Cooperativa Micro 134', 'bus', 2, 0],
        ];

        foreach ($vehiculos as $indice => [$placa, $propietario, $categoria, $ejes, $saldo]) {
            Vehiculo::updateOrCreate(
                ['placa' => $placa],
                [
                    'propietario' => $propietario,
                    'categoria' => $categoria,
                    'ejes' => $ejes,
                    'saldo' => $saldo,
                    'activo' => $indice !== 22, // uno inactivo para probar el estado "no registrada"
                ],
            );
        }
    }
}
