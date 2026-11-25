<?php

namespace App\Console\Commands;

use App\Services\Inferencia\InferenciaLocal;
use Illuminate\Console\Command;
use Throwable;

/**
 * Servicio de inferencia que deja los 4 modelos ONNX cargados en memoria.
 *
 * En PHP cada petición web empieza de cero y vuelve a cargar los modelos (~1 s extra
 * por frame, más el arranque en frío). Este proceso los carga una vez y atiende
 * GET /health y POST /inferir con el mismo contrato que Colab (docs/contrato-inferencia.md).
 *
 * Laravel lo usa si INFERENCE_LOCAL_SERVIDOR apunta aquí; si no responde, sigue con la
 * inferencia normal. Pensado para desarrollo y para un VPS (no para hosting compartido).
 *
 *     php -d extension=ffi -d ffi.enable=1 artisan peaje:servidor-inferencia
 */
class ServidorInferencia extends Command
{
    protected $signature = 'peaje:servidor-inferencia
        {--host=127.0.0.1 : Interfaz donde escucha}
        {--puerto=8100 : Puerto}';

    protected $description = 'Servicio de inferencia con los modelos ONNX cargados en memoria (más rápido por frame)';

    private const MAXIMO_BYTES = 13 * 1024 * 1024;

    public function handle(): int
    {
        if (! extension_loaded('ffi')) {
            $this->error('Falta FFI. Ejecuta: php -d extension=ffi -d ffi.enable=1 artisan peaje:servidor-inferencia');

            return self::FAILURE;
        }

        $local = new InferenciaLocal(config('peaje.inferencia.local'));
        $this->calentar($local);

        $direccion = 'tcp://'.$this->option('host').':'.(int) $this->option('puerto');
        $servidor = @stream_socket_server($direccion, $codigo, $mensaje);

        if (! $servidor) {
            $this->error("No se pudo escuchar en {$direccion}: {$mensaje}");

            return self::FAILURE;
        }

        $this->info("Inferencia lista en http://{$this->option('host')}:{$this->option('puerto')} (Ctrl+C para detener)");

        while ($conexion = @stream_socket_accept($servidor, -1)) {
            try {
                $this->atender($conexion, $local);
            } catch (Throwable $e) {
                $this->responder($conexion, 500, ['error' => $e->getMessage()]);
            } finally {
                @fclose($conexion);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Carga los modelos y hace una inferencia de prueba: la primera siempre es más lenta.
     */
    private function calentar(InferenciaLocal $local): void
    {
        $inicio = hrtime(true);
        $ruta = tempnam(sys_get_temp_dir(), 'peaje').'.jpg';
        $imagen = imagecreatetruecolor(640, 480);
        imagejpeg($imagen, $ruta);

        try {
            $local->enEsteProceso($ruta);
        } finally {
            @unlink($ruta);
        }

        $this->line('Modelos cargados en '.round((hrtime(true) - $inicio) / 1e6).' ms.');
    }

    /**
     * @param  resource  $conexion
     */
    private function atender($conexion, InferenciaLocal $local): void
    {
        stream_set_timeout($conexion, 30);

        // Línea de petición y cabeceras.
        $cabecera = '';
        while (! str_contains($cabecera, "\r\n\r\n") && ! feof($conexion)) {
            $cabecera .= (string) fread($conexion, 8192);
            if (strlen($cabecera) > 65536) {
                $this->responder($conexion, 431, ['error' => 'Cabeceras demasiado grandes.']);

                return;
            }
        }

        [$crudo, $cuerpo] = array_pad(explode("\r\n\r\n", $cabecera, 2), 2, '');
        $lineas = explode("\r\n", $crudo);
        [$metodo, $ruta] = array_pad(explode(' ', array_shift($lineas)), 2, '');
        $ruta = parse_url($ruta, PHP_URL_PATH) ?: '/';

        $cabeceras = [];
        foreach ($lineas as $linea) {
            [$nombre, $valor] = array_pad(explode(':', $linea, 2), 2, '');
            $cabeceras[strtolower(trim($nombre))] = trim($valor);
        }

        if ($metodo === 'GET' && $ruta === '/health') {
            $this->responder($conexion, 200, ['estado' => 'ok', 'modelos' => ['M1', 'M2', 'M3'], 'modelos_en_memoria' => true]);

            return;
        }

        if ($metodo !== 'POST' || $ruta !== '/inferir') {
            $this->responder($conexion, 404, ['error' => 'Ruta no encontrada.']);

            return;
        }

        $token = (string) config('peaje.inferencia.token');
        if ($token !== '' && ! hash_equals($token, $cabeceras['x-api-key'] ?? '')) {
            $this->responder($conexion, 401, ['error' => 'Token inválido.']);

            return;
        }

        $largo = (int) ($cabeceras['content-length'] ?? -1);
        if ($largo < 0 || $largo > self::MAXIMO_BYTES) {
            $this->responder($conexion, $largo < 0 ? 411 : 413, ['error' => 'Falta Content-Length o la imagen es muy grande.']);

            return;
        }

        // Los clientes HTTP piden permiso antes de enviar cuerpos grandes.
        if (strtolower($cabeceras['expect'] ?? '') === '100-continue') {
            fwrite($conexion, "HTTP/1.1 100 Continue\r\n\r\n");
        }

        while (strlen($cuerpo) < $largo && ! feof($conexion)) {
            $cuerpo .= (string) fread($conexion, min(65536, $largo - strlen($cuerpo)));
        }

        $imagen = $this->archivoMultipart($cuerpo, $cabeceras['content-type'] ?? '', 'imagen');
        if ($imagen === null) {
            $this->responder($conexion, 422, ['error' => 'Falta el campo «imagen».']);

            return;
        }

        $temporal = tempnam(sys_get_temp_dir(), 'peaje');
        file_put_contents($temporal, $imagen);

        try {
            $resultado = $local->enEsteProceso($temporal);
        } finally {
            @unlink($temporal);
        }

        $this->responder($conexion, 200, $resultado);
        $this->line(sprintf('%s  %s  %s  %d ms', now()->format('H:i:s'), $resultado['placa'] ?? '—', $resultado['categoria'] ?? '—', $resultado['latencia_ms'] ?? 0));
    }

    /**
     * Contenido del archivo `$campo` de un cuerpo multipart/form-data.
     */
    private function archivoMultipart(string $cuerpo, string $tipo, string $campo): ?string
    {
        if (! preg_match('/boundary="?([^";]+)"?/i', $tipo, $m)) {
            return null;
        }

        foreach (explode('--'.$m[1], $cuerpo) as $parte) {
            [$encabezado, $contenido] = array_pad(explode("\r\n\r\n", $parte, 2), 2, null);

            if ($contenido !== null && preg_match('/name="'.preg_quote($campo, '/').'"/i', $encabezado)) {
                return str_ends_with($contenido, "\r\n") ? substr($contenido, 0, -2) : $contenido;
            }
        }

        return null;
    }

    /**
     * @param  resource  $conexion
     * @param  array<string, mixed>  $datos
     */
    private function responder($conexion, int $codigo, array $datos): void
    {
        $json = json_encode($datos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $texto = [200 => 'OK', 401 => 'Unauthorized', 404 => 'Not Found', 411 => 'Length Required', 413 => 'Payload Too Large',
            422 => 'Unprocessable Content', 431 => 'Request Header Fields Too Large', 500 => 'Internal Server Error'][$codigo] ?? 'OK';

        $salida = "HTTP/1.1 {$codigo} {$texto}\r\nContent-Type: application/json\r\nContent-Length: ".strlen($json)."\r\nConnection: close\r\n\r\n".$json;

        // En un socket, fwrite puede escribir solo una parte.
        while ($salida !== '') {
            $escritos = @fwrite($conexion, $salida);
            if ($escritos === false || $escritos === 0) {
                return;
            }
            $salida = substr($salida, $escritos);
        }
    }
}
