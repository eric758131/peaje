<?php

namespace App\Services\Agente;

use App\Services\Inferencia\Inferencia;
use App\Support\Placa;

/**
 * Consulta por imagen: la inferencia (M1 + OCR) lee la placa y luego se consulta la base.
 * La respuesta se arma sin LLM, así no se pueden inventar datos.
 */
class ConsultaImagen
{
    public function __construct(
        private Inferencia $inferencia,
        private EstadoPlaca $estados,
    ) {}

    /**
     * @param  bool  $conEvidencia  Agrega la imagen anotada (JPEG base64) en la clave `evidencia`.
     * @return array<string, mixed>
     */
    public function consultar(string $rutaImagen, bool $conEvidencia = false): array
    {
        $lectura = $this->inferencia->inferir($rutaImagen);
        $evidencia = $conEvidencia ? ($lectura['evidencia'] ?? null) : null;

        $resultado = $this->resultado($lectura);

        return $evidencia ? $resultado + ['evidencia' => $evidencia] : $resultado;
    }

    /**
     * @param  array<string, mixed>  $lectura
     * @return array<string, mixed>
     */
    private function resultado(array $lectura): array
    {
        $placa = Placa::normalizar($lectura['placa'] ?? null);
        // Qué hay en la imagen (vehículos, tipos, llantas, personas, placas): el agente lo usa después.
        $escena = EscenaImagen::desde($lectura);

        if ($placa === null) {
            return [
                'estado' => 'sin_placa',
                'escena' => $escena,
                'mensaje' => EscenaImagen::texto($escena).' No pude leer una placa legible: envía otra foto donde se vea la placa completa.',
            ];
        }

        $resultado = $this->estados->consultar($placa);
        $resultado['placa_leida'] = $lectura['placa'];
        $resultado['confianza_ocr'] = $lectura['confianza'] ?? null;

        if (($lectura['confianza'] ?? 1) < config('peaje.confianza_minima_ocr')) {
            $resultado['advertencia'] = 'Lectura de baja confianza: confirma la placa antes de actuar.';
        }

        $resultado['escena'] = $escena;
        $resultado['mensaje'] = EscenaImagen::texto($escena)."\n".$this->mensaje($resultado);

        return $resultado;
    }

    /**
     * Texto en español para cada estado (ver EstadoPlaca::texto).
     *
     * @param  array<string, mixed>  $r
     */
    public function mensaje(array $r): string
    {
        return EstadoPlaca::texto($r);
    }
}
