<?php

namespace App\Services\Inferencia\Local;

use OnnxRuntime\ElementType;
use OnnxRuntime\FFI as OrtFFI;
use OnnxRuntime\InferenceSession;
use OnnxRuntime\OrtValue;
use RuntimeException;

/**
 * Ejecuta modelos ONNX desde PHP con ONNX Runtime (extensión FFI).
 *
 * Los tensores se copian como bloques de memoria (FFI::memcpy / FFI::string):
 * convertir millones de números a arreglos de PHP sería demasiado lento.
 */
class MotorOnnx
{
    /** @var array<string, InferenceSession> Una sesión por modelo y por proceso. */
    private static array $sesiones = [];

    /**
     * @param  array{modelos: array<string, string>, hilos: int, onnxruntime_lib: ?string}  $config
     */
    public function __construct(private array $config)
    {
        if (! extension_loaded('ffi')) {
            throw new RuntimeException('La extensión FFI de PHP no está activada.');
        }

        OrtFFI::$lib ??= self::rutaLibreria($config['onnxruntime_lib'] ?? null);
    }

    /**
     * Librería nativa de ONNX Runtime según el sistema operativo.
     */
    public static function rutaLibreria(?string $configurada = null): string
    {
        if (filled($configurada)) {
            return $configurada;
        }

        $carpeta = base_path('modelos/onnxruntime');

        return match (PHP_OS_FAMILY) {
            'Windows' => $carpeta.'/win-x64/onnxruntime.dll',
            'Darwin' => $carpeta.'/osx/libonnxruntime.dylib',
            default => $carpeta.'/linux-x64/libonnxruntime.so',
        };
    }

    public function sesion(string $modelo): InferenceSession
    {
        if (! isset(self::$sesiones[$modelo])) {
            $ruta = $this->config['modelos'][$modelo] ?? null;

            if (! $ruta || ! is_file($ruta)) {
                throw new RuntimeException("No se encontró el modelo «{$modelo}» en {$ruta}.");
            }

            self::$sesiones[$modelo] = new InferenceSession(
                $ruta,
                intraOpNumThreads: max(1, (int) ($this->config['hilos'] ?? 2)),
                interOpNumThreads: 1,
                logSeverityLevel: 3,
            );
        }

        return self::$sesiones[$modelo];
    }

    /**
     * Ejecuta un modelo con una sola entrada.
     *
     * @param  array<int, int>  $forma
     * @param  string  $datos  Bytes de la entrada (float32 little-endian o uint8).
     * @param  array<int, string>|null  $salidas
     * @return array<int, OrtValue>
     */
    public function ejecutar(string $modelo, string $entrada, array $forma, ElementType $tipo, string $datos, ?array $salidas = null): array
    {
        $esperado = array_product($forma) * ($tipo === ElementType::Float ? 4 : 1);

        if (strlen($datos) !== $esperado) {
            throw new RuntimeException("La entrada de «{$modelo}» tiene ".strlen($datos)." bytes y se esperaban {$esperado}.");
        }

        $valor = OrtValue::fromShapeAndType($forma, $tipo);
        \FFI::memcpy($valor->dataPtr(), $datos, $esperado);

        return $this->sesion($modelo)->runWithOrtValues($salidas, [$entrada => $valor]);
    }

    /**
     * Lee un tensor float32 como arreglo plano de PHP (índice desde 0).
     *
     * @return array<int, float>
     */
    public static function floats(OrtValue $valor): array
    {
        $cantidad = array_product($valor->shape());

        if ($cantidad === 0) {
            return [];
        }

        return array_values(unpack('g*', \FFI::string($valor->dataPtr(), $cantidad * 4)));
    }
}
