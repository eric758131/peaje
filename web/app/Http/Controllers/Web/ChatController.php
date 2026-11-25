<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Agente\ConversacionAgente;
use App\Services\Agente\MemoriaChat;
use App\Services\Inferencia\InferenciaException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Chat con el agente (página "Consultar placa" y botón flotante): texto, foto (con o sin pregunta) o video
 * (el navegador envía sus frames uno por uno). Recuerda la última imagen y los últimos mensajes de la sesión.
 */
class ChatController extends Controller
{
    public function index(): View
    {
        return view('chat');
    }

    public function enviar(Request $request, ConversacionAgente $conversacion): JsonResponse
    {
        $memoria = $this->memoria($request);

        if ($request->hasFile('imagen')) {
            $request->validate([
                'imagen' => ['file', 'image', 'max:12288'],
                'mensaje' => ['nullable', 'string', 'max:1000'],
            ]);

            try {
                return response()->json($conversacion->imagen($memoria, $request->file('imagen')->getRealPath(), $request->input('mensaje')));
            } catch (InferenciaException $e) {
                return response()->json(['respuesta' => 'La inferencia no está disponible. Revisa la página Estado.'], 503);
            }
        }

        $datos = $request->validate(['mensaje' => ['required', 'string', 'max:1000']]);

        return response()->json($conversacion->texto($memoria, $datos['mensaje']));
    }

    /** Empieza una conversación nueva (olvida la última imagen y los mensajes). */
    public function reiniciar(Request $request): Response
    {
        $this->memoria($request)->olvidar();
        $request->session()->forget('agente.conversacion');

        return response()->noContent();
    }

    /** Una conversación por sesión (el id se guarda en la sesión: no cambia al regenerarla). */
    private function memoria(Request $request): MemoriaChat
    {
        if (! $request->session()->has('agente.conversacion')) {
            $request->session()->put('agente.conversacion', (string) Str::uuid());
        }

        return MemoriaChat::web($request->session()->get('agente.conversacion'));
    }
}
