<?php

namespace App\Console\Commands;

use App\Services\Inferencia\InferenciaException;
use App\Services\Inferencia\InferenciaLocal;
use Illuminate\Console\Command;

/**
 * Ejecuta los modelos ONNX sobre una imagen e imprime el JSON del contrato de inferencia.
 *
 * Lo usa InferenciaLocal cuando el hosting solo permite FFI en la consola.
 * También sirve para probar a mano:  php artisan peaje:inferir foto.jpg --sin-evidencia
 */
class InferirImagen extends Command
{
    protected $signature = 'peaje:inferir
        {imagen? : Ruta de la imagen}
        {--diagnostico : Solo muestra si FFI y los modelos están disponibles}
        {--sin-evidencia : No incluye la imagen de evidencia en la salida}';

    protected $description = 'Inferencia local (placa, categoría, ejes y pasajeros) con ONNX Runtime en PHP';

    public function handle(): int
    {
        $local = new InferenciaLocal(config('peaje.inferencia.local'));

        if ($this->option('diagnostico')) {
            $this->line(json_encode($local->diagnostico(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $ruta = (string) $this->argument('imagen');

        if (! is_file($ruta)) {
            $this->line(json_encode(['error' => 'No existe la imagen '.$ruta]));

            return self::FAILURE;
        }

        try {
            $resultado = $local->enEsteProceso($ruta);
        } catch (InferenciaException $e) {
            $this->line(json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE));

            return self::FAILURE;
        }

        if ($this->option('sin-evidencia')) {
            unset($resultado['evidencia']);
        }

        $this->line(json_encode($resultado, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
