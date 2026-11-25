<?php

namespace App\Services\Inferencia\Local;

use GdImage;
use OnnxRuntime\ElementType;

/**
 * Ejecuta los modelos sobre una imagen y arma el resultado del contrato de inferencia.
 *
 *  M1 placas-detector.onnx  YOLOv9-t: caja de la placa.
 *     placas-ocr.onnx       CCT (fast-plate-ocr): texto de la placa.
 *  M2 vehiculos-seg.onnx    YOLO26n-seg (COCO): máscara y tipo de vehículo, personas (pasajeros).
 *  M3 llantas-oiv7.onnx     YOLOv8s (Open Images V7): llantas -> keypoint en el centro de cada rueda -> ejes.
 *                           También distingue "Van" para la categoría minibús.
 */
class Analizador
{
    /** COCO (vehiculos-seg) */
    private const COCO_PERSONA = 0;

    private const COCO_CATEGORIA = [2 => 'auto', 3 => 'moto', 5 => 'bus', 7 => 'camion'];

    /** Open Images V7 (llantas-oiv7) */
    private const OI_LLANTA = [582, 536]; // Wheel, Tire

    private const OI_VAN = 564;

    private const OI_CATEGORIA = [90 => 'auto', 522 => 'auto', 342 => 'moto', 73 => 'bus', 558 => 'camion', 564 => 'minibus'];

    private const ALFABETO_OCR = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_';

    private const LADO = 640;

    private const LADO_PLACAS = 384;

    /** @var array<int, float> Prototipos de máscara 32 x 160 x 160 */
    private array $protos = [];

    /** @var array<string, int> */
    private array $tiempos = [];

    /**
     * @param  array{lado_maximo?: int, llantas?: bool}  $config
     */
    public function __construct(private MotorOnnx $motor, private array $config = []) {}

    /**
     * @return array<string, mixed>
     */
    public function analizar(string $ruta): array
    {
        $inicio = hrtime(true);
        $this->tiempos = [];

        $imagen = $this->medir('imagen', fn () => Imagen::cargar($ruta, (int) ($this->config['lado_maximo'] ?? 1280)));
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);

        $lienzo = $this->medir('preparar', fn () => Imagen::letterbox($imagen, self::LADO));

        // M2: segmentación de vehículos y personas.
        $segmentos = $this->medir('m2_segmentacion', fn () => $this->segmentar($lienzo, $ancho, $alto));
        $vehiculos = array_values(array_filter($segmentos, fn ($d) => isset(self::COCO_CATEGORIA[$d['clase']])));
        $personas = array_values(array_filter($segmentos, fn ($d) => $d['clase'] === self::COCO_PERSONA));

        // M3: llantas y vehículos de Open Images (para minibús y como respaldo).
        $abiertos = ($this->config['llantas'] ?? true)
            ? $this->medir('m3_llantas', fn () => $this->detectarAbierto($lienzo, $ancho, $alto))
            : [];

        $vehiculo = $this->vehiculoPrincipal($vehiculos, $abiertos);
        $categoria = $this->categoria($vehiculo, $abiertos);

        [$ejes, $llantas, $ejesEstimados] = $this->ejes($vehiculo, $categoria, $abiertos);
        $pasajeros = $vehiculo ? $this->pasajeros($vehiculo, $personas) : [];

        // M1: placa + OCR.
        $placa = $this->medir('m1_placa_ocr', fn () => $this->leerPlaca($imagen, $vehiculo));

        $resumen = [
            'placa' => $placa['texto'] ?? null,
            'confianza' => isset($placa['confianza']) ? round($placa['confianza'], 4) : null,
            'categoria' => $categoria,
            'ejes' => $ejes,
            'pasajeros' => $vehiculo ? count($pasajeros) : null,
        ];

        $evidencia = $this->medir('evidencia', fn () => Evidencia::dibujar(
            $imagen, $lienzo, $vehiculo, $personas, $llantas, $placa, $resumen, fn ($d) => $this->mascara($d),
        ));

        return $resumen + [
            'latencia_ms' => (int) round((hrtime(true) - $inicio) / 1e6),
            'evidencia' => $evidencia,
            'detalle' => [
                'vehiculo' => $vehiculo ? [
                    'clase' => $categoria,
                    'confianza' => round($vehiculo['score'], 3),
                    'caja' => $this->caja($vehiculo),
                ] : null,
                'placa' => $placa ? [
                    'caja' => $placa['caja'],
                    'confianza_deteccion' => round($placa['deteccion'], 3),
                ] : null,
                // Keypoints de M3: centro de cada llanta visible, en píxeles de la imagen de trabajo.
                'llantas' => array_map(fn ($l) => [(int) round(($l['x1'] + $l['x2']) / 2), (int) round(($l['y1'] + $l['y2']) / 2)], $llantas),
                'ejes_estimados' => $ejesEstimados,
                'vehiculos_detectados' => count($vehiculos),
                'personas_detectadas' => count($personas),
                'imagen' => [$ancho, $alto],
                'tiempos_ms' => $this->tiempos,
            ],
        ];
    }

    /* ---------------------------------------------------------------- M2 */

    /**
     * @param  array{tensor: string, escala: float, px: float, py: float, lado: int}  $lienzo
     * @return array<int, array<string, mixed>>
     */
    private function segmentar(array $lienzo, int $ancho, int $alto): array
    {
        [$salida, $protos] = $this->motor->ejecutar('vehiculos', 'images', [1, 3, self::LADO, self::LADO], ElementType::Float, $lienzo['tensor']);

        $this->protos = MotorOnnx::floats($protos);

        $detecciones = [];
        foreach (array_chunk(MotorOnnx::floats($salida), 38) as $fila) {
            $clase = (int) $fila[5];
            if ($fila[4] < 0.3 || ($clase !== self::COCO_PERSONA && ! isset(self::COCO_CATEGORIA[$clase]))) {
                continue;
            }
            $detecciones[] = $this->deteccion($fila, $lienzo, $ancho, $alto) + ['coef' => array_slice($fila, 6, 32)];
        }

        return Geometria::nms($detecciones, 0.7);
    }

    /**
     * Máscara binaria de una detección en la resolución de los prototipos (160 x 160).
     *
     * @param  array<string, mixed>  $d
     * @return array{u0: int, v0: int, ancho: int, alto: int, pixeles: array<int, bool>}|null
     */
    public function mascara(array $d): ?array
    {
        if (empty($d['coef']) || empty($this->protos)) {
            return null;
        }

        $lado = 160;
        $plano = $lado * $lado;
        $u0 = max(0, (int) floor($d['lb'][0] / 4));
        $v0 = max(0, (int) floor($d['lb'][1] / 4));
        $u1 = min($lado, (int) ceil($d['lb'][2] / 4));
        $v1 = min($lado, (int) ceil($d['lb'][3] / 4));
        $ancho = $u1 - $u0;
        $alto = $v1 - $v0;

        if ($ancho <= 0 || $alto <= 0) {
            return null;
        }

        $suma = array_fill(0, $ancho * $alto, 0.0);
        foreach ($d['coef'] as $c => $k) {
            $base = $c * $plano;
            $i = 0;
            for ($v = $v0; $v < $v1; $v++) {
                $fila = $base + $v * $lado;
                for ($u = $u0; $u < $u1; $u++) {
                    $suma[$i++] += $k * $this->protos[$fila + $u];
                }
            }
        }

        // sigmoide(x) > 0.5  <=>  x > 0
        return ['u0' => $u0, 'v0' => $v0, 'ancho' => $ancho, 'alto' => $alto, 'pixeles' => array_map(fn ($x) => $x > 0, $suma)];
    }

    /* ---------------------------------------------------------------- M3 */

    /**
     * @param  array{tensor: string, escala: float, px: float, py: float, lado: int}  $lienzo
     * @return array<int, array<string, mixed>>
     */
    private function detectarAbierto(array $lienzo, int $ancho, int $alto): array
    {
        [$salida] = $this->motor->ejecutar('llantas', 'images', [1, 3, self::LADO, self::LADO], ElementType::Float, $lienzo['tensor']);

        $detecciones = [];
        foreach (array_chunk(MotorOnnx::floats($salida), 6) as $fila) {
            $clase = (int) $fila[5];
            if ($fila[4] < 0.25 || (! in_array($clase, self::OI_LLANTA, true) && ! isset(self::OI_CATEGORIA[$clase]))) {
                continue;
            }
            $detecciones[] = $this->deteccion($fila, $lienzo, $ancho, $alto);
        }

        return $detecciones;
    }

    /**
     * Ejes = llantas visibles de un costado del vehículo principal.
     *
     * @param  array<string, mixed>|null  $vehiculo
     * @param  array<int, array<string, mixed>>  $abiertos
     * @return array{0: ?int, 1: array<int, array<string, mixed>>, 2: bool}
     */
    private function ejes(?array $vehiculo, ?string $categoria, array $abiertos): array
    {
        if ($vehiculo === null) {
            return [null, [], true];
        }

        $alturaVehiculo = $vehiculo['y2'] - $vehiculo['y1'];
        $margen = 0.03 * ($vehiculo['x2'] - $vehiculo['x1']);

        $llantas = array_filter($abiertos, function ($d) use ($vehiculo, $alturaVehiculo, $margen) {
            if (! in_array($d['clase'], self::OI_LLANTA, true)) {
                return false;
            }
            $cx = ($d['x1'] + $d['x2']) / 2;
            $cy = ($d['y1'] + $d['y2']) / 2;

            return $cx >= $vehiculo['x1'] - $margen && $cx <= $vehiculo['x2'] + $margen
                && $cy >= $vehiculo['y1'] + 0.45 * $alturaVehiculo && $cy <= $vehiculo['y2'] + $margen
                && ($d['y2'] - $d['y1']) >= 0.08 * $alturaVehiculo;
        });

        // "Wheel" y "Tire" pueden marcar la misma rueda.
        $llantas = Geometria::nms(array_values($llantas), 0.4, porClase: false);

        // Las llantas del costado lejano se ven más pequeñas: se descartan.
        if (count($llantas) > 2) {
            $mayor = max(array_map(fn ($l) => $l['y2'] - $l['y1'], $llantas));
            $llantas = array_values(array_filter($llantas, fn ($l) => ($l['y2'] - $l['y1']) >= 0.6 * $mayor));
        }

        usort($llantas, fn ($a, $b) => $a['x1'] <=> $b['x1']);

        $porDefecto = config('peaje.ejes_por_categoria.'.($categoria ?? 'auto'), 2);

        if ($categoria === 'moto') {
            return [2, $llantas, count($llantas) < 2];
        }

        if (count($llantas) >= 2) {
            return [min(9, count($llantas)), $llantas, false];
        }

        return [$porDefecto, $llantas, true];
    }

    /* ---------------------------------------------------------------- Categoría y pasajeros */

    /**
     * El vehículo más grande es el que está pasando por la caseta.
     *
     * @param  array<int, array<string, mixed>>  $vehiculos
     * @param  array<int, array<string, mixed>>  $abiertos
     * @return array<string, mixed>|null
     */
    private function vehiculoPrincipal(array $vehiculos, array $abiertos): ?array
    {
        if ($vehiculos === []) {
            // Respaldo: vehículos de Open Images (sin máscara).
            $vehiculos = array_values(array_filter($abiertos, fn ($d) => isset(self::OI_CATEGORIA[$d['clase']])));
            foreach ($vehiculos as &$v) {
                $v['fuente'] = 'oiv7';
            }
            unset($v);
        }

        if ($vehiculos === []) {
            return null;
        }

        usort($vehiculos, fn ($a, $b) => Geometria::area($b) <=> Geometria::area($a));

        return $vehiculos[0];
    }

    /**
     * @param  array<string, mixed>|null  $vehiculo
     * @param  array<int, array<string, mixed>>  $abiertos
     */
    private function categoria(?array $vehiculo, array $abiertos): ?string
    {
        if ($vehiculo === null) {
            return null;
        }

        if (($vehiculo['fuente'] ?? null) === 'oiv7') {
            return self::OI_CATEGORIA[$vehiculo['clase']];
        }

        $categoria = self::COCO_CATEGORIA[$vehiculo['clase']];

        // COCO no tiene minibús: si Open Images ve una "Van" sobre el mismo vehículo, es minibús.
        if ($categoria !== 'moto') {
            foreach ($abiertos as $d) {
                if ($d['clase'] === self::OI_VAN && Geometria::iou($d, $vehiculo) > 0.5) {
                    return 'minibus';
                }
            }
        }

        return $categoria;
    }

    /**
     * Personas cuya caja queda en su mayor parte dentro del vehículo principal.
     *
     * @param  array<string, mixed>  $vehiculo
     * @param  array<int, array<string, mixed>>  $personas
     * @return array<int, array<string, mixed>>
     */
    private function pasajeros(array $vehiculo, array $personas): array
    {
        $areaVehiculo = Geometria::area($vehiculo);

        return array_values(array_filter($personas, function ($p) use ($vehiculo, $areaVehiculo) {
            $area = Geometria::area($p);

            return $area > 0
                && $area < 0.6 * $areaVehiculo
                && Geometria::interseccion($p, $vehiculo) / $area >= 0.5;
        }));
    }

    /* ---------------------------------------------------------------- M1 */

    /**
     * @param  array<string, mixed>|null  $vehiculo
     * @return array{texto: ?string, confianza: float, deteccion: float, caja: array<int, int>}|null
     */
    private function leerPlaca(GdImage $imagen, ?array $vehiculo): ?array
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $lienzo = Imagen::letterbox($imagen, self::LADO_PLACAS);

        [$salida] = $this->motor->ejecutar('placas', 'images', [1, 3, self::LADO_PLACAS, self::LADO_PLACAS], ElementType::Float, $lienzo['tensor']);

        $placas = [];
        foreach (array_chunk(MotorOnnx::floats($salida), 7) as $fila) {
            // [lote, x1, y1, x2, y2, clase, score]
            if ($fila[6] < 0.4) {
                continue;
            }
            $placas[] = $this->deteccion([$fila[1], $fila[2], $fila[3], $fila[4], $fila[6], 0], $lienzo, $ancho, $alto);
        }

        if ($placas === []) {
            return null;
        }

        // Preferir la placa que está sobre el vehículo principal.
        usort($placas, function ($a, $b) use ($vehiculo) {
            $enA = $vehiculo && Geometria::contieneCentro($vehiculo, $a);
            $enB = $vehiculo && Geometria::contieneCentro($vehiculo, $b);

            return [$enB, $b['score']] <=> [$enA, $a['score']];
        });

        $placa = $placas[0];
        [$x1, $y1, $x2, $y2] = $this->caja($placa);

        if ($x2 - $x1 < 4 || $y2 - $y1 < 4) {
            return null;
        }

        [$salidaOcr] = $this->motor->ejecutar('ocr', 'input', [1, 64, 128, 3], ElementType::UInt8,
            Imagen::recorteRgb($imagen, $x1, $y1, $x2, $y2, 128, 64), ['plate']);

        $texto = '';
        $probabilidades = [];
        foreach (array_chunk(MotorOnnx::floats($salidaOcr), strlen(self::ALFABETO_OCR)) as $posicion) {
            $mejor = max($posicion);
            $caracter = self::ALFABETO_OCR[array_search($mejor, $posicion, true)];
            if ($caracter !== '_') {
                $texto .= $caracter;
                $probabilidades[] = $mejor;
            }
        }

        return [
            'texto' => $texto !== '' ? $texto : null,
            'confianza' => $probabilidades ? array_sum($probabilidades) / count($probabilidades) : 0.0,
            'deteccion' => $placa['score'],
            'caja' => [$x1, $y1, $x2, $y2],
        ];
    }

    /* ---------------------------------------------------------------- Utilidades */

    /**
     * Convierte una fila [x1, y1, x2, y2, score, clase, ...] del lienzo a coordenadas de la imagen.
     *
     * @param  array<int, float>  $fila
     * @param  array{escala: float, px: float, py: float}  $lienzo
     * @return array<string, mixed>
     */
    private function deteccion(array $fila, array $lienzo, int $ancho, int $alto): array
    {
        $x = fn ($v) => max(0.0, min($ancho, ($v - $lienzo['px']) / $lienzo['escala']));
        $y = fn ($v) => max(0.0, min($alto, ($v - $lienzo['py']) / $lienzo['escala']));

        return [
            'x1' => $x($fila[0]), 'y1' => $y($fila[1]), 'x2' => $x($fila[2]), 'y2' => $y($fila[3]),
            'score' => (float) $fila[4],
            'clase' => (int) $fila[5],
            'lb' => [$fila[0], $fila[1], $fila[2], $fila[3]],
        ];
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<int, int>
     */
    private function caja(array $d): array
    {
        return [(int) round($d['x1']), (int) round($d['y1']), (int) round($d['x2']), (int) round($d['y2'])];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $paso
     * @return T
     */
    private function medir(string $nombre, callable $paso): mixed
    {
        $inicio = hrtime(true);
        $resultado = $paso();
        $this->tiempos[$nombre] = (int) round((hrtime(true) - $inicio) / 1e6);

        return $resultado;
    }
}
