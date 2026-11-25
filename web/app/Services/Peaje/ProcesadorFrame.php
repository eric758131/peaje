<?php

namespace App\Services\Peaje;

use App\Models\Caseta;
use App\Models\Paso;
use App\Models\Usuario;
use App\Services\Inferencia\Inferencia;
use App\Services\Inferencia\InferenciaException;
use App\Support\Placa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Un frame de caseta (foto, frame de video o de cámara): inferencia -> paso -> tiempo real.
 * Lo usan la API móvil y la caseta web.
 */
class ProcesadorFrame
{
    public const REGISTRADO = 'registrado';

    public const DUPLICADO = 'duplicado';

    public const SIN_PLACA = 'sin_placa';

    /** Menos caracteres que esto es una lectura incompleta (placa cortada o lejana). */
    private const LARGO_MINIMO = 5;

    public function __construct(private Inferencia $inferencia, private RegistroPaso $registro) {}

    /**
     * Un frame puede traer varios vehículos (el endpoint de Colab devuelve `vehiculos`): se registra
     * un paso por cada vehículo con placa. Los campos de arriba (resultado, paso, lectura) son los del
     * paso principal; `pasos` trae todos. La evidencia es un JPEG en base64 (si la inferencia la generó).
     *
     * @return array{resultado: string, paso: ?Paso, lectura: array<string, mixed>, evidencia: ?string,
     *               pasos: list<array{resultado: string, paso: ?Paso, lectura: array<string, mixed>}>}
     *
     * @throws InferenciaException
     */
    public function procesar(UploadedFile $archivo, Caseta $caseta, ?Usuario $operador): array
    {
        $lectura = $this->inferencia->inferir($archivo->getRealPath());

        $evidencia = $lectura['evidencia'] ?? null;
        unset($lectura['evidencia']);

        // Una sola imagen de evidencia para todos los pasos del frame (se guarda al primer paso).
        $url = null;
        $guardarEvidencia = function () use (&$url, $archivo, $evidencia) {
            return $url ??= $this->guardarEvidencia($archivo, $evidencia);
        };

        // Vehículos con placa. Los que no son el principal suelen estar lejos (placa chica) y el OCR
        // se equivoca más: solo se registran con OCR muy seguro. Igual se ven todos en la evidencia.
        $minimo = (float) config('peaje.confianza_vehiculo_secundario', 0.9);
        $conPlaca = array_values(array_filter($lectura['vehiculos'] ?? [], fn ($v) => filled($v['placa'] ?? null)
            && (($v['principal'] ?? false) || ($v['confianza_placa'] ?? 0) >= $minimo)));

        if (count($conPlaca) <= 1) {
            // Un solo vehículo con placa, o una inferencia que no trae la lista (driver local).
            $r = $this->procesarLectura($lectura, $caseta, $operador, $guardarEvidencia);

            return $r + ['evidencia' => $evidencia, 'pasos' => [$r]];
        }

        $pasos = [];
        foreach ($conPlaca as $v) {
            $pasos[] = $this->procesarLectura([
                'placa' => $v['placa'],
                'confianza' => $v['confianza_placa'] ?? null,
                'categoria' => $v['categoria'] ?? null,
                'ejes' => $v['ejes'] ?? null,
                'pasajeros' => $v['pasajeros'] ?? null,
                'latencia_ms' => $lectura['latencia_ms'] ?? null,
            ], $caseta, $operador, $guardarEvidencia);
        }

        // Principal: el primer paso nuevo; si no hubo, el del vehículo principal del endpoint.
        $principal = collect($pasos)->firstWhere('resultado', self::REGISTRADO)
            ?? collect($pasos)->first(fn ($p) => ($p['lectura']['placa'] ?? null) === ($lectura['placa'] ?? null))
            ?? $pasos[0];

        return [
            'resultado' => $principal['resultado'],
            'paso' => $principal['paso'],
            'lectura' => array_merge($lectura, $principal['lectura']),
            'evidencia' => $evidencia,
            'pasos' => $pasos,
        ];
    }

    /**
     * Una lectura (placa, confianza, categoría, ejes, pasajeros) -> paso nuevo, duplicado o sin placa.
     *
     * @param  array<string, mixed>  $lectura
     * @param  callable(): string  $guardarEvidencia
     * @return array{resultado: string, paso: ?Paso, lectura: array<string, mixed>}
     */
    private function procesarLectura(array $lectura, Caseta $caseta, ?Usuario $operador, callable $guardarEvidencia): array
    {
        $placa = Placa::normalizar($lectura['placa'] ?? null);

        if ($placa === null || strlen($placa) < self::LARGO_MINIMO) {
            return ['resultado' => self::SIN_PLACA, 'paso' => null, 'lectura' => $lectura];
        }

        // Lectura sin formato boliviano: primero se corrige por posición (2424YJ -> 242AYJ).
        // Si sigue sin formato, solo se registra con OCR muy seguro (una placa extranjera bien
        // leída). Si no, es una lectura cortada o borrosa ("7AAAL", "40L1M0L"): no se registra,
        // así no se crean pasos ni alertas de evasión falsos.
        if ($corregida = Placa::corregirFormato($placa)) {
            if ($corregida !== $placa) {
                $lectura['placa_corregida'] = $corregida;
            }
            $placa = $corregida;
        } elseif (($lectura['confianza'] ?? 0) < (float) config('peaje.confianza_sin_formato', 0.9)) {
            return ['resultado' => self::SIN_PLACA, 'paso' => null, 'lectura' => $lectura + ['motivo' => 'lectura_dudosa']];
        }

        // La app y la web mandan varios frames a la vez (2-5 por segundo). Dos frames del mismo
        // vehículo que llegan juntos no deben cobrar dos veces: revisar y registrar va bajo un
        // bloqueo por caseta. La inferencia (lo lento) queda fuera del bloqueo.
        [$resultado, $paso] = Cache::lock('frame-caseta-'.$caseta->id, 15)->block(15, function () use ($placa, $caseta, $lectura, $operador, $guardarEvidencia) {
            // Un video manda muchos frames del mismo vehículo: no se cobra dos veces.
            if ($previo = $this->pasoReciente($placa, $caseta)) {
                return [self::DUPLICADO, $previo];
            }

            return [self::REGISTRADO, $this->registro->registrar($lectura, $caseta, $operador, $guardarEvidencia())];
        });

        return ['resultado' => $resultado, 'paso' => $paso, 'lectura' => $lectura];
    }

    /**
     * Texto para el operador según el resultado de procesar().
     *
     * @param  array{resultado: string, lectura: array<string, mixed>}  $r
     */
    public static function mensaje(array $r): string
    {
        $l = $r['lectura'];

        // Varios vehículos con placa en el mismo frame.
        if (count($r['pasos'] ?? []) > 1) {
            $nuevos = array_values(array_filter($r['pasos'], fn ($p) => $p['resultado'] === self::REGISTRADO));
            $repetidos = count(array_filter($r['pasos'], fn ($p) => $p['resultado'] === self::DUPLICADO));
            $partes = [];
            if ($nuevos) {
                $partes[] = count($nuevos).' paso(s) registrado(s): '.implode(', ', array_map(fn ($p) => $p['paso']->placa_normalizada, $nuevos));
            }
            if ($repetidos) {
                $partes[] = $repetidos.' ya registrado(s) hace poco (no se cobran de nuevo)';
            }

            return $partes ? 'Varios vehículos en el frame. '.implode('. ', $partes).'.' : 'Varios vehículos, ninguna placa completa.';
        }

        return match (true) {
            $r['resultado'] === self::REGISTRADO => isset($l['placa_corregida'])
                ? "Paso registrado. El OCR leyó {$l['placa']} y se corrigió a {$l['placa_corregida']}."
                : 'Paso registrado.',
            $r['resultado'] === self::DUPLICADO => 'Esta placa ya se registró hace poco en esta caseta: no se cobra de nuevo.',
            ($l['motivo'] ?? null) === 'lectura_dudosa' => sprintf(
                'Lectura dudosa (%s, %d %%): no tiene formato de placa boliviana. No se registra; prueba con otro frame.',
                $l['placa'], round(($l['confianza'] ?? 0) * 100),
            ),
            default => 'No se leyó una placa completa en este frame.',
        };
    }

    /**
     * La misma placa (o una variante típica del OCR) en la misma caseta dentro de la ventana.
     */
    private function pasoReciente(string $placa, Caseta $caseta): ?Paso
    {
        $ventana = (int) config('peaje.ventana_duplicado', 60);

        if ($ventana <= 0) {
            return null;
        }

        // Pocos pasos por caseta en la ventana: se comparan en PHP con la regla de Placa::mismoVehiculo
        // (incluye las variantes típicas del OCR y lecturas con letras mal leídas).
        return Paso::query()
            ->where('caseta_id', $caseta->id)
            ->where('created_at', '>=', now()->subSeconds($ventana))
            ->latest('id')
            ->limit(50)
            ->get()
            ->first(fn (Paso $p) => Placa::mismoVehiculo($placa, (string) $p->placa_normalizada)
                || in_array($p->placa_normalizada, Placa::variantesOcr($placa), true));
    }

    /**
     * Guarda la imagen anotada o, si no hay, el frame original.
     */
    private function guardarEvidencia(UploadedFile $archivo, ?string $evidencia): string
    {
        $disco = Storage::disk('public');
        $decodificada = $evidencia ? base64_decode($evidencia, true) : false;

        if ($decodificada) {
            $ruta = 'evidencias/'.now()->format('Y/m/d').'/'.Str::uuid().'.jpg';
            $disco->put($ruta, $decodificada);
        } else {
            $ruta = $archivo->store('evidencias/'.now()->format('Y/m/d'), 'public');
        }

        return $disco->url($ruta);
    }
}
