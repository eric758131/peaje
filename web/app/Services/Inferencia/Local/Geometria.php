<?php

namespace App\Services\Inferencia\Local;

/**
 * Operaciones con cajas ['x1', 'y1', 'x2', 'y2', 'score', 'clase'].
 */
class Geometria
{
    /** @param  array<string, mixed>  $a */
    public static function area(array $a): float
    {
        return max(0.0, $a['x2'] - $a['x1']) * max(0.0, $a['y2'] - $a['y1']);
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public static function interseccion(array $a, array $b): float
    {
        $ancho = min($a['x2'], $b['x2']) - max($a['x1'], $b['x1']);
        $alto = min($a['y2'], $b['y2']) - max($a['y1'], $b['y1']);

        return $ancho > 0 && $alto > 0 ? $ancho * $alto : 0.0;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public static function iou(array $a, array $b): float
    {
        $inter = self::interseccion($a, $b);
        $union = self::area($a) + self::area($b) - $inter;

        return $union > 0 ? $inter / $union : 0.0;
    }

    /**
     * @param  array<string, mixed>  $contenedor
     * @param  array<string, mixed>  $b
     */
    public static function contieneCentro(array $contenedor, array $b): bool
    {
        $cx = ($b['x1'] + $b['x2']) / 2;
        $cy = ($b['y1'] + $b['y2']) / 2;

        return $cx >= $contenedor['x1'] && $cx <= $contenedor['x2'] && $cy >= $contenedor['y1'] && $cy <= $contenedor['y2'];
    }

    /**
     * Supresión de no máximos: conserva la caja de mayor puntaje entre las que se solapan.
     *
     * @param  array<int, array<string, mixed>>  $cajas
     * @return array<int, array<string, mixed>>
     */
    public static function nms(array $cajas, float $umbral, bool $porClase = true): array
    {
        usort($cajas, fn ($a, $b) => $b['score'] <=> $a['score']);

        $elegidas = [];
        foreach ($cajas as $caja) {
            foreach ($elegidas as $elegida) {
                if ((! $porClase || $elegida['clase'] === $caja['clase']) && self::iou($caja, $elegida) > $umbral) {
                    continue 2;
                }
            }
            $elegidas[] = $caja;
        }

        return $elegidas;
    }
}
