<?php

namespace App\Services\Inferencia\Local;

use GdImage;

/**
 * Imagen de evidencia: máscara del vehículo, placa y keypoints de las llantas.
 *
 * Personas (guía del lab: la evidencia muestra solo máscara o conteo, sin rostros): cada una
 * se cubre con un rectángulo sólido y el número va en el encabezado. No se calcula su máscara
 * ni se pixela (eso era lo costoso): un rectángulo cuesta prácticamente 0 ms.
 */
class Evidencia
{
    private const AZUL = [14, 165, 233];

    private const VERDE = [16, 185, 129];

    private const AMBAR = [245, 158, 11];

    private const GRIS = [100, 116, 139];

    private const ROSA = [236, 72, 153];

    /**
     * @param  array{escala: float, px: float, py: float}  $lienzo
     * @param  array<string, mixed>|null  $vehiculo
     * @param  array<int, array<string, mixed>>  $personas
     * @param  array<int, array<string, mixed>>  $llantas
     * @param  array<string, mixed>|null  $placa
     * @param  array<string, mixed>  $resumen
     * @param  callable(array<string, mixed>): ?array<string, mixed>  $mascara
     */
    public static function dibujar(GdImage $imagen, array $lienzo, ?array $vehiculo, array $personas,
        array $llantas, ?array $placa, array $resumen, callable $mascara): string
    {
        imagealphablending($imagen, true);
        $ancho = imagesx($imagen);
        $grosor = max(2, (int) round($ancho / 400));
        $escalaTexto = $ancho >= 1200 ? 2 : 1;

        // 1. Vehículo: máscara semitransparente y contorno.
        if ($vehiculo) {
            if ($m = $mascara($vehiculo)) {
                self::pintarMascara($imagen, $m, $lienzo, self::AZUL, 85);
            }
            self::rectangulo($imagen, $vehiculo, self::AZUL, $grosor);
        }

        // Personas: rectángulo sólido (sin rostro), sin máscara ni pixelado.
        $gris = self::color($imagen, self::GRIS);
        foreach ($personas as $p) {
            imagefilledrectangle($imagen, (int) $p['x1'], (int) $p['y1'], (int) $p['x2'], (int) $p['y2'], $gris);
        }

        // 2. Llantas: keypoint en el centro de cada rueda, numerado.
        foreach ($llantas as $i => $llanta) {
            $cx = (int) round(($llanta['x1'] + $llanta['x2']) / 2);
            $cy = (int) round(($llanta['y1'] + $llanta['y2']) / 2);
            $radio = max(6, (int) round(($llanta['y2'] - $llanta['y1']) * 0.12));
            imagefilledellipse($imagen, $cx, $cy, $radio * 2 + 4, $radio * 2 + 4, self::color($imagen, [255, 255, 255]));
            imagefilledellipse($imagen, $cx, $cy, $radio * 2, $radio * 2, self::color($imagen, self::ROSA));
            self::etiqueta($imagen, $cx + $radio + 2, $cy - 8 * $escalaTexto, (string) ($i + 1), self::ROSA, $escalaTexto);
        }

        // 3. Placa.
        if ($placa) {
            [$x1, $y1, $x2, $y2] = $placa['caja'];
            self::rectangulo($imagen, ['x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2], self::VERDE, $grosor);
            $texto = ($placa['texto'] ?? '?').(isset($placa['confianza']) ? ' '.round($placa['confianza'] * 100).'%' : '');
            self::etiqueta($imagen, $x1, max(0, $y1 - 18 * $escalaTexto), $texto, self::VERDE, $escalaTexto);
        }

        // 4. Resumen arriba.
        $partes = array_filter([
            $resumen['categoria'] ? strtoupper($resumen['categoria']) : 'SIN VEHICULO',
            $resumen['ejes'] !== null ? $resumen['ejes'].' ejes' : null,
            $resumen['pasajeros'] !== null ? $resumen['pasajeros'].' pasajero(s)' : null,
            'placa '.($resumen['placa'] ?? '---'),
        ]);
        self::etiqueta($imagen, 0, 0, ' '.implode('  |  ', $partes).' ', [15, 23, 42], $escalaTexto);

        return Imagen::jpegBase64($imagen);
    }

    /**
     * @param  array{u0: int, v0: int, ancho: int, alto: int, pixeles: array<int, bool>}  $m
     * @param  array{escala: float, px: float, py: float}  $lienzo
     * @param  array<int, int>  $rgb
     * @param  int  $alfa  0 = opaco, 127 = transparente
     */
    private static function pintarMascara(GdImage $imagen, array $m, array $lienzo, array $rgb, int $alfa): void
    {
        $capa = imagecreatetruecolor($m['ancho'], $m['alto']);
        imagealphablending($capa, false);
        imagesavealpha($capa, true);
        $transparente = imagecolorallocatealpha($capa, 0, 0, 0, 127);
        $relleno = imagecolorallocatealpha($capa, $rgb[0], $rgb[1], $rgb[2], $alfa);
        imagefill($capa, 0, 0, $transparente);

        $i = 0;
        for ($v = 0; $v < $m['alto']; $v++) {
            for ($u = 0; $u < $m['ancho']; $u++) {
                if ($m['pixeles'][$i++]) {
                    imagesetpixel($capa, $u, $v, $relleno);
                }
            }
        }

        // Prototipo (160) -> lienzo (640) -> imagen.
        $x = (int) round(($m['u0'] * 4 - $lienzo['px']) / $lienzo['escala']);
        $y = (int) round(($m['v0'] * 4 - $lienzo['py']) / $lienzo['escala']);
        $w = (int) round($m['ancho'] * 4 / $lienzo['escala']);
        $h = (int) round($m['alto'] * 4 / $lienzo['escala']);

        imagecopyresampled($imagen, $capa, $x, $y, 0, 0, $w, $h, $m['ancho'], $m['alto']);
    }

    /**
     * @param  array<string, mixed>  $caja
     * @param  array<int, int>  $rgb
     */
    private static function rectangulo(GdImage $imagen, array $caja, array $rgb, int $grosor): void
    {
        imagesetthickness($imagen, $grosor);
        imagerectangle($imagen, (int) $caja['x1'], (int) $caja['y1'], (int) $caja['x2'], (int) $caja['y2'], self::color($imagen, $rgb));
        imagesetthickness($imagen, 1);
    }

    /**
     * Texto blanco sobre fondo de color. GD solo trae fuentes pequeñas: se dibuja y se amplía.
     *
     * @param  array<int, int>  $fondo
     */
    private static function etiqueta(GdImage $imagen, int $x, int $y, string $texto, array $fondo, int $escala): void
    {
        $fuente = 5;
        $ancho = imagefontwidth($fuente) * strlen($texto) + 6;
        $alto = imagefontheight($fuente) + 4;

        $caja = imagecreatetruecolor($ancho, $alto);
        imagefill($caja, 0, 0, imagecolorallocate($caja, $fondo[0], $fondo[1], $fondo[2]));
        imagestring($caja, $fuente, 3, 2, $texto, imagecolorallocate($caja, 255, 255, 255));

        imagecopyresized($imagen, $caja, $x, $y, 0, 0, $ancho * $escala, $alto * $escala, $ancho, $alto);
    }

    /** @param  array<int, int>  $rgb */
    private static function color(GdImage $imagen, array $rgb): int
    {
        return imagecolorallocate($imagen, $rgb[0], $rgb[1], $rgb[2]);
    }
}
