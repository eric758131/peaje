<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Agente\ConversacionAgente;
use App\Services\Agente\MemoriaChat;
use App\Services\Inferencia\InferenciaException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgenteController extends Controller
{
    /**
     * Mensaje al agente conversacional (Qwen en Colab, con function calling).
     *
     * Acepta texto (`mensaje`) o una foto (`imagen`, multipart) con una pregunta opcional en `mensaje`.
     * Con la foto responde qué hay (vehículos, tipos, llantas, personas, placas) y el estado de la placa.
     *
     * Memoria: envía el mismo `conversacion` en cada mensaje para que recuerde la última foto y lo
     * conversado ("¿cuántas llantas hay?"). Si no lo envías, la respuesta trae uno nuevo para reutilizar.
     *
     * Responde solo con datos de la base y de la imagen. `modo` es `llm` si respondió Qwen,
     * o `sin_llm` si la respuesta se armó directo desde la base (Colab no disponible).
     *
     * @response array{respuesta: string, modo: string, conversacion: string, evidencia?: string|null, detalle?: array<string, mixed>}
     */
    public function chat(Request $request, ConversacionAgente $agente): JsonResponse
    {
        $datos = $request->validate([
            'imagen' => ['nullable', 'file', 'image', 'max:12288'],
            'mensaje' => [$request->hasFile('imagen') ? 'nullable' : 'required', 'string', 'max:1000'],
            'conversacion' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $id = $datos['conversacion'] ?? (string) Str::uuid();
        $memoria = MemoriaChat::api($id);

        if ($request->hasFile('imagen')) {
            try {
                $resultado = $agente->imagen($memoria, $request->file('imagen')->getRealPath(), $datos['mensaje'] ?? null);
            } catch (InferenciaException $e) {
                return response()->json(['message' => 'La inferencia no está disponible.', 'conversacion' => $id], 503);
            }

            return response()->json($resultado + ['conversacion' => $id]);
        }

        return response()->json($agente->texto($memoria, $datos['mensaje']) + ['conversacion' => $id]);
    }
}
