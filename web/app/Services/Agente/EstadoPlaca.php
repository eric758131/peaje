<?php

namespace App\Services\Agente;

use App\Models\Alerta;
use App\Models\Paso;
use App\Models\Vehiculo;
use App\Support\Placa;

/**
 * Decide en cuál de los 4 estados está una placa, leyendo solo la base de datos.
 *
 *  - registrada_con_paso:      registrada y con pasos
 *  - registrada_sin_paso:      registrada, nunca pasó por el peaje
 *  - identificada_no_registrada: la cámara la vio, pero no está registrada (posible evasión)
 *  - no_existe:                no hay ningún registro
 */
class EstadoPlaca
{
    public const REGISTRADA_CON_PASO = 'registrada_con_paso';

    public const REGISTRADA_SIN_PASO = 'registrada_sin_paso';

    public const IDENTIFICADA_NO_REGISTRADA = 'identificada_no_registrada';

    public const NO_EXISTE = 'no_existe';

    public const FORMATO_INVALIDO = 'formato_invalido';

    /**
     * @return array<string, mixed>
     */
    public function consultar(?string $textoPlaca): array
    {
        $placa = Placa::normalizar($textoPlaca);

        if (! Placa::esValida($placa)) {
            // Un error de OCR (ej. 1234A8C) casi siempre rompe el formato: sugerimos la placa real si existe.
            return [
                'placa' => $placa,
                'estado' => self::FORMATO_INVALIDO,
                'mensaje' => 'El texto no tiene formato de placa boliviana (3–4 dígitos y 3 letras).',
                'sugerencias' => $placa ? $this->sugerencias($placa) : [],
            ];
        }

        $vehiculo = Vehiculo::where('placa', $placa)->first();

        if ($vehiculo) {
            $ultimo = Paso::with('caseta')
                ->where('placa_normalizada', $placa)
                ->latest('created_at')
                ->first();

            if (! $ultimo) {
                return $this->respuesta($placa, self::REGISTRADA_SIN_PASO, $vehiculo);
            }

            return $this->respuesta($placa, self::REGISTRADA_CON_PASO, $vehiculo, [
                'ultimo_paso' => [
                    'fecha' => $ultimo->created_at->toDateTimeString(),
                    'caseta' => $ultimo->caseta->nombre,
                    'categoria' => $ultimo->categoria_detectada,
                    'ejes' => $ultimo->ejes_detectados,
                    'monto' => (float) $ultimo->monto,
                    'estado_paso' => $ultimo->estado,
                ],
            ]);
        }

        if (Paso::where('placa_normalizada', $placa)->exists()) {
            return $this->respuesta($placa, self::IDENTIFICADA_NO_REGISTRADA, null, [
                'pasos_sin_registro' => Paso::where('placa_normalizada', $placa)->count(),
                'alertas_abiertas' => Alerta::where('atendida', false)
                    ->whereHas('paso', fn ($q) => $q->where('placa_normalizada', $placa))
                    ->count(),
            ]);
        }

        return $this->respuesta($placa, self::NO_EXISTE, null);
    }

    /**
     * Variantes de la placa que sí existen en la base (errores típicos de OCR).
     *
     * @return list<string>
     */
    public function sugerencias(string $placa): array
    {
        $variantes = Placa::variantesOcr($placa);

        if ($variantes === []) {
            return [];
        }

        return Vehiculo::whereIn('placa', $variantes)->pluck('placa')->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function respuesta(string $placa, string $estado, ?Vehiculo $vehiculo, array $datos = []): array
    {
        $sugerencias = $estado === self::NO_EXISTE || $estado === self::IDENTIFICADA_NO_REGISTRADA
            ? $this->sugerencias($placa)
            : [];

        return array_merge([
            'placa' => $placa,
            'estado' => $estado,
            'registrada' => $vehiculo !== null,
            'categoria' => $vehiculo?->categoria,
            'ejes' => $vehiculo?->ejes,
            'saldo' => $vehiculo ? (float) $vehiculo->saldo : null,
            'sugerencias' => $sugerencias,
        ], $datos);
    }

    /**
     * Texto en español para cada estado. No agrega datos que no vengan de la base.
     *
     * @param  array<string, mixed>  $r
     */
    public static function texto(array $r): string
    {
        $placa = $r['placa'] ?? '';

        $texto = match ($r['estado']) {
            EstadoPlaca::REGISTRADA_CON_PASO => sprintf(
                'La placa %s está registrada. Último paso: %s, caseta %s, categoría %s, monto Bs %s.',
                $placa,
                $r['ultimo_paso']['fecha'],
                $r['ultimo_paso']['caseta'],
                $r['ultimo_paso']['categoria'],
                number_format($r['ultimo_paso']['monto'], 2),
            ),
            EstadoPlaca::REGISTRADA_SIN_PASO => "La placa {$placa} está registrada, pero nunca ha pasado por el peaje.",
            EstadoPlaca::IDENTIFICADA_NO_REGISTRADA => "La cámara identificó la placa {$placa}, pero no está registrada. Posible evasión.",
            EstadoPlaca::NO_EXISTE => "No existe ningún registro de la placa {$placa}.",
            default => 'Leí «'.$placa.'». '.($r['mensaje'] ?? 'No se pudo consultar la placa.'),
        };

        if (! empty($r['sugerencias'])) {
            $texto .= ' ¿Quisiste decir '.implode(' o ', $r['sugerencias']).'?';
        }

        return $texto;
    }
}
