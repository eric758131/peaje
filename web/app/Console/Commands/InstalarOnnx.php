<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use ZipArchive;

/**
 * Descarga la librería nativa de ONNX Runtime (paquete oficial de NuGet) a modelos/onnxruntime/.
 * Solo hace falta si esa carpeta no se subió con el proyecto.
 */
class InstalarOnnx extends Command
{
    private const VERSION = '1.32.0';

    private const SHA256 = 'a9150bc2992cbbb6722070da9910e1ffa989a8bfc67bdd5bb8e745ca755641db';

    private const ARCHIVOS = [
        'linux-x64' => ['libonnxruntime.so', 'libonnxruntime_providers_shared.so'],
        'win-x64' => ['onnxruntime.dll', 'onnxruntime_providers_shared.dll'],
    ];

    protected $signature = 'peaje:instalar-onnx {--plataforma= : linux-x64 o win-x64 (por defecto, la de este servidor)}';

    protected $description = 'Descarga ONNX Runtime para la inferencia local en PHP';

    public function handle(): int
    {
        $plataforma = $this->option('plataforma') ?: (PHP_OS_FAMILY === 'Windows' ? 'win-x64' : 'linux-x64');

        if (! isset(self::ARCHIVOS[$plataforma])) {
            $this->error('Plataforma no soportada: '.$plataforma);

            return self::FAILURE;
        }

        $destino = base_path('modelos/onnxruntime/'.$plataforma);
        $paquete = storage_path('app/onnxruntime-'.self::VERSION.'.nupkg');
        $url = 'https://api.nuget.org/v3-flatcontainer/microsoft.ml.onnxruntime/'.self::VERSION.'/microsoft.ml.onnxruntime.'.self::VERSION.'.nupkg';

        if (! is_file($paquete) || hash_file('sha256', $paquete) !== self::SHA256) {
            $this->info('Descargando ONNX Runtime '.self::VERSION.' (≈180 MB)…');
            Http::timeout(900)->sink($paquete)->get($url)->throw();
        }

        if (hash_file('sha256', $paquete) !== self::SHA256) {
            @unlink($paquete);
            $this->error('La descarga no coincide con la suma de verificación.');

            return self::FAILURE;
        }

        if (! is_dir($destino)) {
            mkdir($destino, 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($paquete) !== true) {
            $this->error('No se pudo abrir el paquete (falta la extensión zip de PHP).');

            return self::FAILURE;
        }

        foreach (self::ARCHIVOS[$plataforma] as $archivo) {
            $contenido = $zip->getFromName("runtimes/{$plataforma}/native/{$archivo}");
            if ($contenido === false) {
                $this->error("No está {$archivo} en el paquete.");

                return self::FAILURE;
            }
            file_put_contents($destino.'/'.$archivo, $contenido);
            $this->line("✔ {$destino}/{$archivo}");
        }

        $zip->close();
        @unlink($paquete);

        $this->info('Listo. Revisa la página Estado.');

        return self::SUCCESS;
    }
}
