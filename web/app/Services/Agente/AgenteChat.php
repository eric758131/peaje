<?php

namespace App\Services\Agente;

use App\Services\Inferencia\ConexionColab;
use App\Support\Placa;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Agente conversacional con function calling.
 *
 * El LLM (Qwen, servido con Ollama en el mismo Colab de la inferencia: POST {link}/agente/chat)
 * solo redacta. Los datos salen de la herramienta `consultar_placa`, que Laravel ejecuta sobre su
 * base de datos (EstadoPlaca). Para que responda rápido y no invente:
 *  1. Si el mensaje trae una placa, Laravel la consulta antes y le pasa el resultado al LLM
 *     como si el LLM hubiera llamado a la herramienta (una sola vuelta al LLM).
 *  2. Si no trae placa, el LLM puede pedir la herramienta (hasta MAX_VUELTAS vueltas).
 *  3. Si Colab no responde, se contesta igual con el texto armado desde la base (sin LLM).
 */
class AgenteChat
{
    private const MAX_VUELTAS = 3;

    private const HERRAMIENTA = 'consultar_placa';

    /** Último modo de respuesta: "llm" o "sin_llm" (para la interfaz y las pruebas). */
    public string $modo = 'sin_llm';

    /** Máximo de mensajes anteriores que se le pasan al LLM (memoria corta de la conversación). */
    public const HISTORIAL = 8;

    /** @var array<string, mixed>|null Lo que se detectó en la última imagen que envió el usuario. */
    private ?array $escena = null;

    /** @var list<array{role: string, content: string}> */
    private array $historial = [];

    private string $pregunta = '';

    public function __construct(private EstadoPlaca $estados) {}

    /**
     * @param  array<string, mixed>|null  $escena  EscenaImagen::desde() de la última imagen (para preguntas sobre ella).
     * @param  list<array{role: string, content: string}>  $historial  Mensajes anteriores (usuario y agente).
     */
    public function responder(string $mensaje, ?array $escena = null, array $historial = []): string
    {
        $this->escena = $escena;
        $this->historial = array_slice($historial, -self::HISTORIAL);
        $this->pregunta = $mensaje;
        $consultas = array_map(fn ($placa) => $this->consultar($placa), Placa::enTexto($mensaje));

        try {
            $respuesta = $this->conLlm($mensaje, $consultas);
            $this->modo = 'llm';

            return $respuesta;
        } catch (\Throwable $e) {
            Log::info('Agente sin LLM: '.$e->getMessage());
            $this->modo = 'sin_llm';

            return $this->sinLlm($consultas);
        }
    }

    /**
     * Respuesta sin LLM para imágenes: si la inferencia no lee placa, se pide otra foto.
     */
    public function mensajeSinPlaca(): string
    {
        return 'No pude leer una placa legible en la imagen. Envía otra foto donde se vea la placa completa.';
    }

    /**
     * @param  list<array<string, mixed>>  $consultas
     */
    private function conLlm(string $mensaje, array $consultas): string
    {
        $url = ConexionColab::url();
        if (ConexionColab::driver() !== 'http' || $url === '') {
            throw new \RuntimeException('No hay conexión con Colab configurada.');
        }

        $mensajes = [['role' => 'system', 'content' => $this->instrucciones()]];

        if ($this->escena !== null) {
            $mensajes[] = ['role' => 'system', 'content' => 'Datos de la ÚLTIMA IMAGEN que envió el usuario, detectados por los modelos de visión '
                .'(úsalos si pregunta cuántos vehículos, de qué tipo, cuántas llantas, ejes, personas o qué placas hay): '
                .json_encode($this->escena, JSON_UNESCAPED_UNICODE)
                .'. Resumen: '.EscenaImagen::texto($this->escena)];
        }

        foreach ($this->historial as $anterior) {
            $mensajes[] = ['role' => $anterior['role'], 'content' => $anterior['content']];
        }

        $mensajes[] = ['role' => 'user', 'content' => $mensaje];

        if ($consultas !== []) {
            $mensajes[] = [
                'role' => 'assistant',
                'content' => '',
                'tool_calls' => array_map(fn ($c) => ['function' => ['name' => self::HERRAMIENTA, 'arguments' => ['placa' => $c['placa']]]], $consultas),
            ];
            foreach ($consultas as $consulta) {
                $mensajes[] = $this->resultadoHerramienta($consulta);
            }
        }

        for ($vuelta = 0; $vuelta < self::MAX_VUELTAS; $vuelta++) {
            $respuesta = Http::timeout((int) config('peaje.agente.timeout'))
                ->connectTimeout(10)
                ->acceptJson()
                ->withHeaders(array_filter(['X-API-Key' => ConexionColab::token()]))
                ->post($url.'/agente/chat', [
                    'mensajes' => $mensajes,
                    // Con los datos ya consultados no hace falta ofrecer la herramienta: responde en una vuelta.
                    'herramientas' => $consultas === [] ? [$this->herramienta()] : [],
                ]);

            if (! $respuesta->successful()) {
                throw new \RuntimeException('El agente de Colab respondió '.$respuesta->status().'.');
            }

            $llm = $respuesta->json('mensaje') ?? [];
            $llamadas = $llm['tool_calls'] ?? [];

            if ($llamadas === []) {
                $texto = trim((string) ($llm['content'] ?? ''));

                if ($texto === '') {
                    throw new \RuntimeException('El LLM respondió vacío.');
                }

                return $this->verificar($texto, $consultas);
            }

            $mensajes[] = ['role' => 'assistant', 'content' => (string) ($llm['content'] ?? ''), 'tool_calls' => $llamadas];
            foreach ($llamadas as $llamada) {
                $argumentos = $llamada['function']['arguments'] ?? [];
                $argumentos = is_string($argumentos) ? (json_decode($argumentos, true) ?: []) : $argumentos;
                $consulta = ($llamada['function']['name'] ?? '') === self::HERRAMIENTA
                    ? $this->consultar($argumentos['placa'] ?? null)
                    : ['error' => 'Herramienta desconocida.'];
                $consultas[] = $consulta;
                $mensajes[] = $this->resultadoHerramienta($consulta);
            }
        }

        throw new \RuntimeException('El LLM no terminó de responder.');
    }

    /**
     * Control de lo que redactó el LLM (los modelos chicos a veces se saltan o inventan algo):
     *  - si nombra una placa que no salió de la base, se descarta y se usa el texto de la base;
     *  - si la base sugirió placas ("¿Quisiste decir…?") y el LLM no las nombró, se agregan.
     *
     * @param  list<array<string, mixed>>  $consultas
     */
    private function verificar(string $texto, array $consultas): string
    {
        // Placas que el LLM puede nombrar: las consultadas, las sugeridas, las de la imagen y las ya habladas.
        $conocidas = $this->escena['placas_leidas'] ?? [];
        foreach ($this->historial as $anterior) {
            array_push($conocidas, ...Placa::enTexto($anterior['content'], 10));
        }
        $sugeridas = [];
        foreach ($consultas as $consulta) {
            $conocidas[] = $consulta['placa'] ?? null;
            foreach ($consulta['sugerencias'] ?? [] as $sugerencia) {
                $conocidas[] = $sugeridas[] = $sugerencia;
            }
        }

        if (array_diff(Placa::enTexto($texto, 10), array_filter($conocidas)) !== []) {
            Log::info('Agente: el LLM nombró una placa que no vino de la base; se responde sin LLM.', ['texto' => $texto]);

            return $this->sinLlm($consultas);
        }

        $faltan = array_values(array_filter(array_unique($sugeridas), fn ($p) => ! str_contains(strtoupper($texto), $p)));
        if ($faltan !== []) {
            $texto .= ' ¿Quisiste decir '.implode(' o ', $faltan).'?';
        }

        return $texto;
    }

    /**
     * @param  list<array<string, mixed>>  $consultas
     */
    private function sinLlm(array $consultas): string
    {
        $sobreLaImagen = preg_match('/llanta|rueda|veh[ií]cul|auto|carro|tipo|persona|pasajer|imagen|foto|cu[aá]nt|eje|cami[oó]n|bus|moto/iu', $this->pregunta);

        if ($consultas === [] && $this->escena !== null && $sobreLaImagen) {
            return EscenaImagen::texto($this->escena);
        }

        if ($consultas === []) {
            return 'Escríbeme la placa que quieres consultar (por ejemplo 1234ABC) o envíame una foto, y te digo su estado según el sistema.';
        }

        return implode("\n", array_map(fn ($c) => $c['resumen'], $consultas));
    }

    /** @return array<string, mixed> */
    private function consultar(?string $placa): array
    {
        $resultado = $this->estados->consultar($placa);
        $resultado['resumen'] = EstadoPlaca::texto($resultado);

        return $resultado;
    }

    /** @param  array<string, mixed>  $consulta */
    private function resultadoHerramienta(array $consulta): array
    {
        return ['role' => 'tool', 'tool_name' => self::HERRAMIENTA, 'content' => json_encode($consulta, JSON_UNESCAPED_UNICODE)];
    }

    private function herramienta(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => self::HERRAMIENTA,
                'description' => 'Consulta en la base de datos del peaje el estado de una placa: registrada con paso, registrada sin paso, identificada no registrada o no existe.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'placa' => ['type' => 'string', 'description' => 'Placa tal como la escribió el usuario (ej. 1234ABC).'],
                    ],
                    'required' => ['placa'],
                ],
            ],
        ];
    }

    private function instrucciones(): string
    {
        return <<<'TXT'
Eres el asistente del sistema de control de peaje de Bolivia. Respondes en español, en 1 a 3 frases, claro y amable.

Reglas:
1. Los datos de una placa SOLO salen de la herramienta consultar_placa. Nunca inventes placas, fechas, montos, casetas ni estados.
2. Cuando tengas el resultado de la herramienta, explica su campo "resumen" con tus palabras, sin agregar datos que no estén en el resultado.
3. Estados: registrada_con_paso (di fecha, caseta, categoría y monto del último paso), registrada_sin_paso (nunca pasó por el peaje), identificada_no_registrada (la cámara la vio pero no está registrada: posible evasión), no_existe (no hay ningún registro), formato_invalido (pide revisar la placa).
4. Si el resultado trae "sugerencias", pregunta "¿Quisiste decir ...?" con esas placas.
5. Si pregunta qué hay en la imagen (cuántos vehículos, de qué tipo, cuántas llantas, ejes, personas o placas), responde SOLO con los "Datos de la ÚLTIMA IMAGEN" y solo lo que preguntó, sin repetir respuestas anteriores. Si no hay datos de imagen, pídele que envíe una foto.
6. Si no da una placa ni pregunta por la imagen, pídele la placa. Si pregunta otra cosa (tarifas, rutas, horarios, etc.), responde solo que puedes consultar placas y analizar fotos del peaje; no supongas ni expliques nada más.
TXT;
    }
}
