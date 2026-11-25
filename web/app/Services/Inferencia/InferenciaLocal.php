<?php

namespace App\Services\Inferencia;

use App\Services\Inferencia\Local\Analizador;
use App\Services\Inferencia\Local\MotorOnnx;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Inferencia dentro del propio servidor PHP, sin Python.
 *
 * Usa la extensión FFI para llamar a ONNX Runtime. Muchos hostings compartidos dejan FFI
 * solo para la consola (ffi.enable = preload). En ese caso el modo "auto" ejecuta
 * `php artisan peaje:inferir` en un proceso aparte, donde FFI sí está permitido.
 */
class InferenciaLocal implements Inferencia
{
    /**
     * @param  array<string, mixed>  $config  config('peaje.inferencia.local')
     */
    public function __construct(private array $config, private int $timeout = 60) {}

    public function inferir(string $rutaImagen): array
    {
        // Si el servicio con los modelos en memoria está corriendo, se usa (es ~2-3 veces más rápido).
        // Si no responde, se sigue con la inferencia normal.
        if ($servidor = $this->servidor()) {
            try {
                return $this->enServidor($servidor, $rutaImagen);
            } catch (ConnectionException) {
                // Servicio apagado: modo normal.
            }
        }

        return match ($this->modo()) {
            'ffi' => $this->enEsteProceso($rutaImagen),
            'cli' => $this->enConsola($rutaImagen),
            default => throw new InferenciaException('La inferencia local no está disponible: falta la extensión FFI de PHP. Revisa la página Estado.'),
        };
    }

    private function servidor(): ?string
    {
        $url = rtrim((string) ($this->config['servidor'] ?? ''), '/');

        return $url !== '' ? $url : null;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    private function enServidor(string $url, string $rutaImagen): array
    {
        $respuesta = Http::connectTimeout(1)->timeout($this->timeout)->acceptJson()
            ->withHeaders(array_filter(['X-API-Key' => config('peaje.inferencia.token')]))
            ->attach('imagen', file_get_contents($rutaImagen), basename($rutaImagen))
            ->post($url.'/inferir');

        if (! $respuesta->successful() || ! is_array($respuesta->json())) {
            throw new InferenciaException('El servicio de inferencia respondió con error: '.($respuesta->json('error') ?? $respuesta->status()));
        }

        return $respuesta->json();
    }

    private function servidorResponde(string $url): bool
    {
        try {
            return Http::connectTimeout(1)->timeout(3)->get($url.'/health')->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * Ejecuta los modelos aquí mismo. Lo usa también el comando peaje:inferir.
     *
     * @return array<string, mixed>
     */
    public function enEsteProceso(string $rutaImagen): array
    {
        try {
            return (new Analizador(new MotorOnnx($this->config), $this->config))->analizar($rutaImagen);
        } catch (Throwable $e) {
            throw new InferenciaException('Error en la inferencia local: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * ffi: FFI permitido en esta petición. cli: hay que usar la consola. null: no hay forma.
     */
    public function modo(): ?string
    {
        $configurado = $this->config['modo'] ?? 'auto';

        if ($configurado === 'ffi' || ($configurado === 'auto' && self::ffiPermitidoAqui())) {
            return 'ffi';
        }

        if (($configurado === 'cli' || $configurado === 'auto') && self::puedeLanzarProcesos()) {
            return 'cli';
        }

        return null;
    }

    public static function ffiPermitidoAqui(): bool
    {
        if (! extension_loaded('ffi')) {
            return false;
        }

        $valor = strtolower((string) ini_get('ffi.enable'));

        return in_array($valor, ['1', 'true', 'on'], true) || ($valor === 'preload' && PHP_SAPI === 'cli');
    }

    public static function puedeLanzarProcesos(): bool
    {
        $deshabilitadas = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return function_exists('proc_open') && ! in_array('proc_open', $deshabilitadas, true);
    }

    public function phpConsola(): string
    {
        if (filled($this->config['php_cli'] ?? null)) {
            return $this->config['php_cli'];
        }

        // En la consola PHP_BINARY es el propio php. En la web (FPM/LSAPI) no sirve.
        return PHP_SAPI === 'cli' ? PHP_BINARY : 'php';
    }

    /**
     * @return array<string, mixed>
     */
    private function enConsola(string $rutaImagen): array
    {
        $resultado = $this->ejecutarConsola(['peaje:inferir', $rutaImagen]);

        if (isset($resultado['error'])) {
            throw new InferenciaException('Error en la inferencia local (consola): '.$resultado['error']);
        }

        return $resultado;
    }

    /**
     * @param  array<int, string>  $argumentos
     * @return array<string, mixed>
     */
    private function ejecutarConsola(array $argumentos): array
    {
        $extra = $this->config['php_cli_args'] ?? [];
        $activarFfi = ['-d', 'extension=ffi', '-d', 'ffi.enable=1'];
        $clave = 'inferencia.consola_activa_ffi.'.md5($this->phpConsola());

        // XAMPP y algunos hostings traen la extensión FFI instalada pero apagada en php.ini.
        // Si ya se sabe que este PHP lo necesita, se activa directamente.
        if (Cache::get($clave) && ! in_array('extension=ffi', $extra, true)) {
            return $this->lanzarConsola($argumentos, [...$extra, ...$activarFfi]);
        }

        $resultado = $this->lanzarConsola($argumentos, $extra);

        $sinFfi = str_contains((string) ($resultado['error'] ?? ''), 'FFI')
            || (array_key_exists('ffi_permitido', $resultado) && ! $resultado['ffi_permitido']);

        // Se reintenta una vez activándola solo para ese proceso.
        if ($sinFfi && ! in_array('extension=ffi', $extra, true)) {
            $reintento = $this->lanzarConsola($argumentos, [...$extra, ...$activarFfi]);

            if (! isset($reintento['error']) && ($reintento['ffi_permitido'] ?? true)) {
                Cache::forever($clave, true);

                return $reintento;
            }
        }

        return $resultado;
    }

    /**
     * @param  array<int, string>  $argumentos
     * @param  array<int, string>  $extra  Argumentos para el PHP (ej. -d extension=ffi)
     * @return array<string, mixed>
     */
    private function lanzarConsola(array $argumentos, array $extra): array
    {
        $comando = [
            $this->phpConsola(), '-d', 'memory_limit=1024M', ...$extra,
            base_path('artisan'), ...$argumentos, '--no-ansi',
        ];

        $proceso = @proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias, base_path());

        if (! is_resource($proceso)) {
            throw new InferenciaException('No se pudo iniciar el proceso de inferencia ('.$this->phpConsola().').');
        }

        stream_set_blocking($tuberias[1], false);
        stream_set_blocking($tuberias[2], false);

        $salida = $errores = '';
        $limite = microtime(true) + $this->timeout;

        while (true) {
            $salida .= (string) stream_get_contents($tuberias[1]);
            $errores .= (string) stream_get_contents($tuberias[2]);

            if (! proc_get_status($proceso)['running']) {
                $salida .= (string) stream_get_contents($tuberias[1]);
                $errores .= (string) stream_get_contents($tuberias[2]);
                break;
            }

            if (microtime(true) > $limite) {
                proc_terminate($proceso);
                throw new InferenciaException("La inferencia superó {$this->timeout} s.");
            }

            usleep(20_000);
        }

        fclose($tuberias[1]);
        fclose($tuberias[2]);
        proc_close($proceso);

        // La última línea con JSON es la respuesta (por si el PHP imprime avisos antes).
        foreach (array_reverse(preg_split('/\R/', trim($salida))) as $linea) {
            $json = json_decode($linea, true);
            if (is_array($json)) {
                return $json;
            }
        }

        throw new InferenciaException('La consola no devolvió un resultado válido. '.mb_substr(trim($errores ?: $salida), 0, 300));
    }

    public function estaDisponible(): array
    {
        $diagnostico = $this->diagnostico();
        $modo = $this->modo();

        $faltan = array_keys(array_filter($diagnostico['modelos'], fn ($m) => ! $m['existe']));

        if ($faltan) {
            return ['disponible' => false, 'driver' => 'local', 'detalle' => 'Faltan modelos: '.implode(', ', $faltan).'.', 'diagnostico' => $diagnostico];
        }

        if (! $diagnostico['libreria']['existe']) {
            return ['disponible' => false, 'driver' => 'local', 'detalle' => 'Falta la librería de ONNX Runtime en '.$diagnostico['libreria']['ruta'].'.', 'diagnostico' => $diagnostico];
        }

        if (($servidor = $this->servidor()) && $this->servidorResponde($servidor)) {
            return ['disponible' => true, 'driver' => 'local', 'detalle' => "Servicio de inferencia con los modelos en memoria ({$servidor}).", 'diagnostico' => $diagnostico];
        }

        if ($modo === 'ffi') {
            return ['disponible' => true, 'driver' => 'local', 'detalle' => 'Modelos ONNX dentro de PHP (FFI en la misma petición).', 'diagnostico' => $diagnostico];
        }

        if ($modo === 'cli') {
            try {
                $consola = $this->ejecutarConsola(['peaje:inferir', '--diagnostico']);
            } catch (InferenciaException $e) {
                return ['disponible' => false, 'driver' => 'local', 'detalle' => $e->getMessage(), 'diagnostico' => $diagnostico];
            }

            $diagnostico['consola'] = $consola;

            return ($consola['ffi_permitido'] ?? false)
                ? ['disponible' => true, 'driver' => 'local', 'detalle' => 'Modelos ONNX en PHP de consola ('.$this->phpConsola().').', 'diagnostico' => $diagnostico]
                : ['disponible' => false, 'driver' => 'local', 'detalle' => 'El PHP de consola no tiene FFI activado. Actívalo en cPanel → Select PHP Version → Extensions → ffi.', 'diagnostico' => $diagnostico];
        }

        return ['disponible' => false, 'driver' => 'local', 'detalle' => 'Falta la extensión FFI de PHP (cPanel → Select PHP Version → Extensions → ffi).', 'diagnostico' => $diagnostico];
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnostico(): array
    {
        $libreria = MotorOnnx::rutaLibreria($this->config['onnxruntime_lib'] ?? null);

        return [
            'php' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'ffi_cargada' => extension_loaded('ffi'),
            'ffi_enable' => ini_get('ffi.enable'),
            'ffi_permitido' => self::ffiPermitidoAqui(),
            'proc_open' => self::puedeLanzarProcesos(),
            'modo_configurado' => $this->config['modo'] ?? 'auto',
            'modo_efectivo' => $this->modo(),
            'php_consola' => $this->phpConsola(),
            'gd' => extension_loaded('gd'),
            'memoria' => ini_get('memory_limit'),
            'libreria' => ['ruta' => $libreria, 'existe' => is_file($libreria)],
            'modelos' => array_map(fn ($ruta) => [
                'archivo' => basename($ruta),
                'existe' => is_file($ruta),
                'mb' => is_file($ruta) ? round(filesize($ruta) / 1048576, 1) : null,
            ], $this->config['modelos'] ?? []),
        ];
    }
}
