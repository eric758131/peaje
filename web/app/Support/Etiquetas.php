<?php

namespace App\Support;

/**
 * Textos y colores de categorías y estados para las vistas (y para el JavaScript del panel).
 */
final class Etiquetas
{
    public const CATEGORIAS = [
        'moto' => 'Moto',
        'auto' => 'Auto',
        'minibus' => 'Minibús',
        'camion' => 'Camión',
        'bus' => 'Bus',
    ];

    /** estado => [texto, clases de color] */
    public const ESTADOS = [
        'cobrado' => ['Cobrado', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
        'sin_saldo' => ['Sin saldo', 'bg-amber-50 text-amber-800 ring-amber-600/20'],
        'no_registrado' => ['No registrado', 'bg-rose-50 text-rose-700 ring-rose-600/20'],
        'no_registrada' => ['Placa no registrada', 'bg-rose-50 text-rose-700 ring-rose-600/20'],
        'duplicado' => ['Ya registrado', 'bg-slate-100 text-slate-700 ring-slate-500/20'],
        'sin_placa' => ['Sin placa', 'bg-slate-100 text-slate-700 ring-slate-500/20'],
        'registrado' => ['Registrado', 'bg-sky-50 text-sky-700 ring-sky-600/20'],
        'activo' => ['Activo', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
        'inactivo' => ['Inactivo', 'bg-slate-100 text-slate-600 ring-slate-500/20'],
        'administrador' => ['Administrador', 'bg-violet-50 text-violet-700 ring-violet-600/20'],
        'operador' => ['Operador', 'bg-sky-50 text-sky-700 ring-sky-600/20'],
    ];

    public static function categoria(?string $categoria): string
    {
        return self::CATEGORIAS[$categoria] ?? ($categoria ? ucfirst($categoria) : '—');
    }

    public static function estado(?string $estado): string
    {
        return self::ESTADOS[$estado][0] ?? ($estado ?? '—');
    }

    public static function colorEstado(?string $estado): string
    {
        return self::ESTADOS[$estado][1] ?? 'bg-slate-100 text-slate-700 ring-slate-500/20';
    }

    public static function bs(float|string|null $monto): string
    {
        return 'Bs '.number_format((float) $monto, 2, ',', '.');
    }

    /**
     * Para el JavaScript (panel en vivo, caseta web).
     *
     * @return array<string, mixed>
     */
    public static function paraJs(): array
    {
        return [
            'categorias' => self::CATEGORIAS,
            'estados' => array_map(fn ($e) => ['texto' => $e[0], 'clases' => $e[1]], self::ESTADOS),
        ];
    }
}
