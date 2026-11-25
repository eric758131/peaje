<?php

namespace App\Services\Inferencia;

/**
 * Inferencia de los tres modelos sobre una imagen.
 *
 * Implementaciones:
 *  - InferenciaLocal: modelos ONNX dentro de PHP (sin Python).
 *  - InferenciaHttp:  endpoint externo (Colab o RunPod) en INFERENCE_URL.
 *
 * Contrato del resultado: docs/contrato-inferencia.md
 */
interface Inferencia
{
    /**
     * @return array{placa: ?string, confianza: ?float, categoria: ?string, ejes: ?int, pasajeros: ?int, latencia_ms: ?int, evidencia?: ?string, detalle?: array<string, mixed>}
     *
     * @throws InferenciaException
     */
    public function inferir(string $rutaImagen): array;

    /**
     * @return array{disponible: bool, detalle: string, driver: string, diagnostico?: array<string, mixed>}
     */
    public function estaDisponible(): array;
}
