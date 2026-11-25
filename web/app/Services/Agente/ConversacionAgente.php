<?php

namespace App\Services\Agente;

use App\Services\Inferencia\InferenciaException;

/**
 * Un turno de conversación con el agente, igual para la web y la app móvil:
 *  - texto: responde Qwen (o la base, si Colab no está) con la memoria de la conversación;
 *  - imagen: la inferencia describe lo que hay (vehículos, tipos, llantas, personas, placas) y el
 *    estado de la placa; si además trae una pregunta, la responde el agente.
 */
class ConversacionAgente
{
    public function __construct(
        private AgenteChat $agente,
        private ConsultaImagen $imagen,
    ) {}

    /** @return array{respuesta: string, modo: string} */
    public function texto(MemoriaChat $memoria, string $mensaje): array
    {
        $respuesta = $this->agente->responder($mensaje, $memoria->escena(), $memoria->historial());
        $memoria->recordar($mensaje, $respuesta);

        return ['respuesta' => $respuesta, 'modo' => $this->agente->modo];
    }

    /**
     * @return array{respuesta: string, modo: string, detalle: array<string, mixed>, evidencia: ?string}
     *
     * @throws InferenciaException si la inferencia no está disponible.
     */
    public function imagen(MemoriaChat $memoria, string $rutaImagen, ?string $pregunta = null): array
    {
        $resultado = $this->imagen->consultar($rutaImagen, conEvidencia: true);
        $evidencia = $resultado['evidencia'] ?? null;
        unset($resultado['evidencia']);
        $memoria->guardarEscena($resultado['escena'] ?? null);

        $respuesta = $resultado['mensaje'];
        $modo = 'inferencia';   // la descripción sale de los modelos de visión (no hace falta el LLM)
        $pregunta = trim((string) $pregunta);
        if ($pregunta !== '') {
            $respuesta .= "\n\n".$this->agente->responder($pregunta, $resultado['escena'] ?? null, $memoria->historial());
            $modo = $this->agente->modo;
        }

        $memoria->recordar('[envió una foto]'.($pregunta !== '' ? ' '.$pregunta : ''), $respuesta);

        return [
            'respuesta' => $respuesta,
            'modo' => $modo,
            'detalle' => $resultado,
            'evidencia' => $evidencia ? 'data:image/jpeg;base64,'.$evidencia : null,
        ];
    }
}
