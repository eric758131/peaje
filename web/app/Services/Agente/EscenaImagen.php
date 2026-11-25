<?php

namespace App\Services\Agente;

use App\Support\Placa;

/**
 * Resumen de lo que los modelos de visión detectaron en una imagen: cuántos vehículos y de qué tipo,
 * cuántas llantas y personas, y qué placas se leyeron. Sale solo de la respuesta de la inferencia.
 */
class EscenaImagen
{
    private const SINGULAR = ['moto' => 'moto', 'auto' => 'auto', 'minibus' => 'minibús', 'camion' => 'camión', 'bus' => 'bus'];

    private const PLURAL = ['moto' => 'motos', 'auto' => 'autos', 'minibus' => 'minibuses', 'camion' => 'camiones', 'bus' => 'buses'];

    /**
     * @param  array<string, mixed>  $lectura  Respuesta de la inferencia (/inferir).
     * @return array{vehiculos: int, por_tipo: array<string, int>, solo_principal: bool, llantas: int, personas: int, placas_leidas: list<string>, lista: list<array<string, mixed>>}
     */
    public static function desde(array $lectura): array
    {
        $detalle = is_array($lectura['detalle'] ?? null) ? $lectura['detalle'] : [];
        $vehiculos = $lectura['vehiculos'] ?? null;

        // Respuesta sin la lista de vehículos (driver local): solo se conoce el tipo del vehículo principal.
        $soloPrincipal = ! is_array($vehiculos);
        if ($soloPrincipal) {
            $vehiculos = ($lectura['categoria'] ?? null) ? [[
                'categoria' => $lectura['categoria'],
                'ejes' => $lectura['ejes'] ?? null,
                'pasajeros' => $lectura['pasajeros'] ?? 0,
                'placa' => $lectura['placa'] ?? null,
                'llantas' => $detalle['llantas'] ?? [],
            ]] : [];
        }

        $lista = [];
        $porTipo = [];
        foreach ($vehiculos as $v) {
            $tipo = (string) ($v['categoria'] ?? 'auto');
            $porTipo[$tipo] = ($porTipo[$tipo] ?? 0) + 1;
            $lista[] = [
                'tipo' => $tipo,
                'ejes' => $v['ejes'] ?? null,
                'llantas_visibles' => count($v['llantas'] ?? []),
                'personas' => (int) ($v['pasajeros'] ?? 0),
                'placa' => Placa::normalizar($v['placa'] ?? null),
            ];
        }
        arsort($porTipo);

        $placas = $detalle['placas_leidas'] ?? array_column($lista, 'placa');
        $total = (int) ($detalle['vehiculos_detectados'] ?? count($lista));

        return [
            'vehiculos' => $total,
            'por_tipo' => $porTipo,
            // true = solo se sabe el tipo del vehículo más grande (no hay lista de todos).
            'solo_principal' => $soloPrincipal && $total > count($lista),
            'llantas' => (int) ($detalle['llantas_detectadas'] ?? array_sum(array_column($lista, 'llantas_visibles'))),
            'personas' => (int) ($detalle['personas_detectadas'] ?? array_sum(array_column($lista, 'personas'))),
            'placas_leidas' => array_values(array_unique(array_filter(array_map(fn ($p) => Placa::normalizar($p), $placas)))),
            'lista' => $lista,
        ];
    }

    /**
     * "En la imagen detecté 3 vehículos (2 autos y 1 camión), 10 llantas y 1 persona. Placas leídas: 1234ABC."
     *
     * @param  array<string, mixed>  $e
     */
    public static function texto(array $e): string
    {
        $partes = [];

        if ($e['vehiculos'] > 0) {
            $tipos = [];
            foreach ($e['por_tipo'] as $tipo => $n) {
                $tipos[] = $n.' '.($n === 1 ? (self::SINGULAR[$tipo] ?? $tipo) : (self::PLURAL[$tipo] ?? $tipo));
            }
            $detalleTipos = match (true) {
                $tipos === [] => '',
                ! empty($e['solo_principal']) => ' (el más grande es '.(self::SINGULAR[array_key_first($e['por_tipo'])] ?? array_key_first($e['por_tipo'])).')',
                default => ' ('.self::lista($tipos).')',
            };
            $partes[] = self::cantidad($e['vehiculos'], 'vehículo', 'vehículos').$detalleTipos;
        } else {
            $partes[] = 'ningún vehículo';
        }

        $partes[] = self::cantidad($e['llantas'], 'llanta', 'llantas');
        $partes[] = self::cantidad($e['personas'], 'persona', 'personas');

        $texto = 'En la imagen detecté '.self::lista($partes).'.';

        if ($e['placas_leidas'] !== []) {
            $texto .= ' Placas leídas: '.implode(', ', $e['placas_leidas']).'.';
        }

        return $texto;
    }

    private static function cantidad(int $n, string $singular, string $plural): string
    {
        return $n.' '.($n === 1 ? $singular : $plural);
    }

    /** @param  list<string>  $items */
    private static function lista(array $items): string
    {
        return count($items) > 1 ? implode(', ', array_slice($items, 0, -1)).' y '.end($items) : ($items[0] ?? '');
    }
}
