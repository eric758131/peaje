<?php

namespace Tests\Feature\Web;

use App\Models\Usuario;
use App\Models\Vehiculo;
use App\Support\Placa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Agente conversacional: Qwen en Colab (POST {link}/agente/chat) con function calling.
 */
class AgenteChatTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        config(['peaje.inferencia.driver' => 'http', 'peaje.inferencia.url' => 'https://colab.example.com', 'peaje.inferencia.token' => 'tok']);
        $this->usuario = Usuario::create(['email' => 'op@test.com', 'password' => 'password', 'nombre' => 'Op', 'rol' => Usuario::OPERADOR, 'activo' => true]);
        Vehiculo::create(['placa' => '1234ABC', 'propietario' => 'Ana', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 50, 'activo' => true]);
    }

    private function chat(string $mensaje)
    {
        return $this->actingAs($this->usuario)->postJson('/chat', ['mensaje' => $mensaje]);
    }

    public function test_encuentra_placas_en_el_texto(): void
    {
        $this->assertSame(['1234ABC'], Placa::enTexto('¿la 1234-abc pasó hoy?'));
        $this->assertSame(['1234ABC', '567XYZ'], Placa::enTexto('compara 1234 ABC con 567xyz'));
        $this->assertSame(['1234A8C'], Placa::enTexto('placa 1234A8C'));      // mal escrita: igual se consulta
        $this->assertSame([], Placa::enTexto('hola, ¿cómo funciona el peaje en 2026?'));
    }

    public function test_con_placa_en_el_mensaje_le_pasa_los_datos_de_la_base_al_llm(): void
    {
        Http::fake(['colab.example.com/agente/chat' => Http::response(['mensaje' => ['role' => 'assistant', 'content' => 'La 1234ABC está registrada y aún no pasó por el peaje.']])]);

        $this->chat('¿Qué pasa con la 1234-abc?')
            ->assertOk()
            ->assertJsonPath('modo', 'llm')
            ->assertJsonPath('respuesta', 'La 1234ABC está registrada y aún no pasó por el peaje.');

        Http::assertSent(function (Request $r) {
            $mensajes = $r['mensajes'];
            $herramienta = json_decode(end($mensajes)['content'], true);

            return $r->header('X-API-Key') === ['tok']
                && $mensajes[0]['role'] === 'system'
                && $mensajes[2]['tool_calls'][0]['function']['arguments']['placa'] === '1234ABC'
                && $herramienta['estado'] === 'registrada_sin_paso'        // dato real de la base
                && $r['herramientas'] === [];                               // una sola vuelta
        });
    }

    public function test_sin_placa_el_llm_puede_pedir_la_herramienta(): void
    {
        Http::fake(['colab.example.com/agente/chat' => Http::sequence()
            ->push(['mensaje' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [['function' => ['name' => 'consultar_placa', 'arguments' => ['placa' => '9999ZZZ']]]]]])
            ->push(['mensaje' => ['role' => 'assistant', 'content' => 'No hay ningún registro de la 9999ZZZ.']]),
        ]);

        $this->chat('consulta la placa nueve nueve nueve nueve zzz')
            ->assertOk()->assertJsonPath('respuesta', 'No hay ningún registro de la 9999ZZZ.');

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => count($r['herramientas']) === 1 && $r['herramientas'][0]['function']['name'] === 'consultar_placa');
        Http::assertSent(fn (Request $r) => str_contains(end($r->data()['mensajes'])['content'] ?? '', '"estado":"no_existe"'));
    }

    public function test_si_colab_falla_responde_con_la_base_sin_llm(): void
    {
        Http::fake(['colab.example.com/*' => Http::response(['detail' => 'El agente no está cargado'], 503)]);

        $this->chat('estado de 1234ABC')
            ->assertOk()
            ->assertJsonPath('modo', 'sin_llm')
            ->assertJsonPath('respuesta', 'La placa 1234ABC está registrada, pero nunca ha pasado por el peaje.');
    }

    public function test_sugiere_la_placa_correcta_aunque_colab_este_apagado(): void
    {
        config(['peaje.inferencia.driver' => 'local']);

        $this->chat('¿y la 1234A8C?')
            ->assertOk()
            ->assertJsonPath('modo', 'sin_llm')
            ->assertJsonPath('respuesta', fn ($r) => str_contains($r, '¿Quisiste decir 1234ABC?'));
    }

    public function test_agrega_la_sugerencia_si_el_llm_la_olvida(): void
    {
        Http::fake(['colab.example.com/agente/chat' => Http::response(['mensaje' => ['role' => 'assistant', 'content' => 'La placa 1234A8C no tiene un formato válido.']])]);

        $this->chat('¿y la 1234A8C?')
            ->assertOk()->assertJsonPath('modo', 'llm')
            ->assertJsonPath('respuesta', 'La placa 1234A8C no tiene un formato válido. ¿Quisiste decir 1234ABC?');
    }

    public function test_descarta_la_respuesta_si_el_llm_inventa_una_placa(): void
    {
        Http::fake(['colab.example.com/agente/chat' => Http::response(['mensaje' => ['role' => 'assistant', 'content' => 'La 1234ABC pasó ayer junto con la 777QQQ.']])]);

        $this->chat('estado de 1234ABC')
            ->assertOk()
            ->assertJsonPath('respuesta', 'La placa 1234ABC está registrada, pero nunca ha pasado por el peaje.');
    }

    /** Respuesta de /inferir con 3 vehículos (2 autos y 1 camión), 10 llantas y 2 personas. */
    private function inferenciaConEscena(): array
    {
        return [
            'placa' => 'MGX791', 'confianza' => 0.9, 'categoria' => 'auto', 'ejes' => 2, 'pasajeros' => 1, 'latencia_ms' => 300,
            'vehiculos' => [
                ['categoria' => 'camion', 'ejes' => 3, 'pasajeros' => 1, 'placa' => null, 'llantas' => [[1, 1], [2, 2], [3, 3]]],
                ['categoria' => 'auto', 'ejes' => 2, 'pasajeros' => 1, 'placa' => 'MGX791', 'llantas' => [[1, 1], [2, 2]]],
                ['categoria' => 'auto', 'ejes' => 2, 'pasajeros' => 0, 'placa' => null, 'llantas' => [[1, 1], [2, 2]]],
            ],
            'detalle' => ['vehiculos_detectados' => 3, 'llantas_detectadas' => 10, 'personas_detectadas' => 2, 'placas_leidas' => ['MGX791']],
        ];
    }

    public function test_la_foto_describe_vehiculos_tipos_llantas_y_personas(): void
    {
        Http::fake(['colab.example.com/inferir' => Http::response($this->inferenciaConEscena())]);

        $this->actingAs($this->usuario)
            ->post('/chat', ['imagen' => \Illuminate\Http\UploadedFile::fake()->image('calle.jpg')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('detalle.escena.vehiculos', 3)
            ->assertJsonPath('respuesta', fn ($r) => str_starts_with($r, 'En la imagen detecté 3 vehículos (2 autos y 1 camión), 10 llantas y 2 personas. Placas leídas: MGX791.'));
    }

    public function test_recuerda_la_ultima_foto_para_preguntar_despues(): void
    {
        Http::fake([
            'colab.example.com/inferir' => Http::response($this->inferenciaConEscena()),
            'colab.example.com/agente/chat' => Http::response(['mensaje' => ['role' => 'assistant', 'content' => 'Hay 3 vehículos: 2 autos y 1 camión, con 10 llantas.']]),
        ]);

        $this->actingAs($this->usuario)->post('/chat', ['imagen' => \Illuminate\Http\UploadedFile::fake()->image('calle.jpg')], ['Accept' => 'application/json']);

        $this->chat('¿cuántas llantas y vehículos y de qué tipo hay?')
            ->assertOk()->assertJsonPath('respuesta', 'Hay 3 vehículos: 2 autos y 1 camión, con 10 llantas.');

        // El LLM recibió los datos de la imagen y la conversación anterior.
        Http::assertSent(function (Request $r) {
            if (! str_ends_with($r->url(), '/agente/chat')) {
                return false;
            }
            $textos = collect($r['mensajes'])->pluck('content')->implode("\n");

            return str_contains($textos, 'ÚLTIMA IMAGEN') && str_contains($textos, '"llantas":10') && str_contains($textos, '[envió una foto]');
        });
    }

    public function test_sin_colab_responde_lo_de_la_foto_con_los_datos_guardados(): void
    {
        Http::fake(['colab.example.com/inferir' => Http::response($this->inferenciaConEscena()), 'colab.example.com/agente/chat' => Http::response('', 503)]);
        $this->actingAs($this->usuario)->post('/chat', ['imagen' => \Illuminate\Http\UploadedFile::fake()->image('calle.jpg')], ['Accept' => 'application/json']);

        $this->chat('¿cuántas llantas hay?')
            ->assertOk()->assertJsonPath('modo', 'sin_llm')
            ->assertJsonPath('respuesta', 'En la imagen detecté 3 vehículos (2 autos y 1 camión), 10 llantas y 2 personas. Placas leídas: MGX791.');
    }

    public function test_foto_con_pregunta_responde_las_dos_cosas_y_nueva_conversacion_olvida_la_foto(): void
    {
        Http::fake([
            'colab.example.com/inferir' => Http::response($this->inferenciaConEscena()),
            'colab.example.com/agente/chat' => Http::response(['mensaje' => ['role' => 'assistant', 'content' => 'Hay 2 personas.']]),
        ]);

        $this->actingAs($this->usuario)
            ->post('/chat', ['imagen' => \Illuminate\Http\UploadedFile::fake()->image('calle.jpg'), 'mensaje' => '¿cuántas personas hay?'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('respuesta', fn ($r) => str_contains($r, '10 llantas') && str_ends_with($r, 'Hay 2 personas.'));

        $this->actingAs($this->usuario)->post('/chat/reiniciar')->assertNoContent();

        // Ya no recuerda la foto: sin Colab, pide una placa o una foto.
        config(['peaje.inferencia.driver' => 'local']);
        $this->chat('¿cuántas llantas hay?')->assertJsonPath('respuesta', fn ($r) => str_starts_with($r, 'Escríbeme la placa'));
    }

    public function test_la_app_envia_una_foto_y_pregunta_despues_con_la_misma_conversacion(): void
    {
        Http::fake([
            'colab.example.com/inferir' => Http::response($this->inferenciaConEscena()),
            'colab.example.com/agente/chat' => Http::response('', 503),   // sin LLM: responde con lo guardado
        ]);

        $api = $this->actingAs($this->usuario, 'sanctum');
        $id = $api->post('/api/v1/agente/chat', ['imagen' => \Illuminate\Http\UploadedFile::fake()->image('calle.jpg')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('detalle.escena.llantas', 10)
            ->assertJsonPath('evidencia', null)
            ->json('conversacion');
        $this->assertNotEmpty($id);

        $api->postJson('/api/v1/agente/chat', ['mensaje' => '¿cuántas llantas hay?', 'conversacion' => $id])
            ->assertOk()->assertJsonPath('conversacion', $id)
            ->assertJsonPath('respuesta', fn ($r) => str_contains($r, '10 llantas'));

        // Otra conversación no ve esa foto.
        $api->postJson('/api/v1/agente/chat', ['mensaje' => '¿cuántas llantas hay?', 'conversacion' => 'otra'])
            ->assertJsonPath('respuesta', fn ($r) => str_starts_with($r, 'Escríbeme la placa'));

        $api->postJson('/api/v1/agente/chat', ['mensaje' => 'hola', 'conversacion' => 'no válido!'])->assertUnprocessable();
    }

    public function test_la_api_del_agente_usa_el_mismo_llm(): void
    {
        Http::fake(['colab.example.com/agente/chat' => Http::response(['mensaje' => ['role' => 'assistant', 'content' => 'Registrada.']])]);

        $this->actingAs($this->usuario, 'sanctum')->postJson('/api/v1/agente/chat', ['mensaje' => '1234ABC'])
            ->assertOk()->assertJsonPath('respuesta', 'Registrada.')->assertJsonPath('modo', 'llm');
    }
}
