<?php

namespace Tests\Feature\Api;

use App\Models\Caseta;
use App\Models\Paso;
use App\Models\Usuario;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los 4 estados del agente, consultados por la API con texto.
 */
class AgenteEstadoTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = Usuario::create([
            'email' => 'admin@test.com', 'password' => 'password', 'nombre' => 'Admin',
            'rol' => Usuario::ADMINISTRADOR, 'activo' => true,
        ]);
    }

    private function consultar(string $placa)
    {
        return $this->actingAs($this->usuario, 'sanctum')->postJson('/api/v1/placas/consultar', ['placa' => $placa]);
    }

    public function test_registrada_sin_paso(): void
    {
        Vehiculo::create(['placa' => '1111AAA', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 0, 'activo' => true]);

        $this->consultar('1111-aaa')->assertOk()->assertJsonPath('estado', 'registrada_sin_paso');
    }

    public function test_registrada_con_paso(): void
    {
        $vehiculo = Vehiculo::create(['placa' => '2222BBB', 'categoria' => 'moto', 'ejes' => 2, 'saldo' => 0, 'activo' => true]);
        $caseta = Caseta::create(['nombre' => 'Sur', 'activa' => true]);
        Paso::create([
            'placa_normalizada' => '2222BBB', 'vehiculo_id' => $vehiculo->id, 'categoria_detectada' => 'moto',
            'ejes_detectados' => 2, 'monto' => 5, 'caseta_id' => $caseta->id, 'estado' => Paso::COBRADO,
        ]);

        $this->consultar('2222BBB')
            ->assertOk()
            ->assertJsonPath('estado', 'registrada_con_paso')
            ->assertJsonPath('ultimo_paso.caseta', 'Sur');
    }

    public function test_identificada_no_registrada(): void
    {
        $caseta = Caseta::create(['nombre' => 'Centro', 'activa' => true]);
        Paso::create([
            'placa_normalizada' => '3333CCC', 'monto' => 0, 'caseta_id' => $caseta->id,
            'estado' => Paso::NO_REGISTRADO,
        ]);

        $this->consultar('3333CCC')->assertOk()->assertJsonPath('estado', 'identificada_no_registrada');
    }

    public function test_no_existe(): void
    {
        $this->consultar('9999ZZZ')->assertOk()->assertJsonPath('estado', 'no_existe');
    }

    public function test_sugiere_coincidencias_ocr_cuando_el_formato_no_cuadra(): void
    {
        // La placa real está en la base; el OCR leyó "1234A8C" (B -> 8), que ni siquiera tiene formato válido
        Vehiculo::create(['placa' => '1234ABC', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 0, 'activo' => true]);

        $this->consultar('1234A8C')
            ->assertOk()
            ->assertJsonPath('estado', 'formato_invalido')
            ->assertJsonPath('sugerencias.0', '1234ABC');
    }

    public function test_formato_invalido(): void
    {
        $this->consultar('ABC')->assertOk()->assertJsonPath('estado', 'formato_invalido');
    }

    public function test_sin_colab_el_agente_responde_con_los_datos_de_la_base(): void
    {
        config(['peaje.inferencia.driver' => 'local']);

        $this->actingAs($this->usuario, 'sanctum')
            ->postJson('/api/v1/agente/chat', ['mensaje' => 'hola'])
            ->assertOk()->assertJsonPath('modo', 'sin_llm')
            ->assertJsonPath('respuesta', fn ($r) => str_contains($r, 'Escríbeme la placa'));
    }
}
