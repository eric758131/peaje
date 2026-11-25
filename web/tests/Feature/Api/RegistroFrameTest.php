<?php

namespace Tests\Feature\Api;

use App\Models\Alerta;
use App\Models\Caseta;
use App\Models\Paso;
use App\Models\Tarifa;
use App\Models\Usuario;
use App\Models\Vehiculo;
use Database\Seeders\TarifaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistroFrameTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $operador;

    private Caseta $caseta;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['peaje.inferencia.url' => 'https://inferencia.test']);

        $this->seed(TarifaSeeder::class);
        $this->operador = Usuario::create([
            'email' => 'operador@test.com', 'password' => 'password', 'nombre' => 'Operador',
            'rol' => Usuario::OPERADOR, 'activo' => true,
        ]);
        $this->caseta = Caseta::create(['nombre' => 'Norte', 'activa' => true]);
    }

    private function frame(): UploadedFile
    {
        return UploadedFile::fake()->image('frame.jpg');
    }

    public function test_rechaza_frame_sin_token(): void
    {
        $this->postJson('/api/v1/pasos/frame', ['imagen' => $this->frame(), 'caseta_id' => $this->caseta->id])
            ->assertUnauthorized();
    }

    public function test_cobra_a_un_vehiculo_registrado_con_saldo(): void
    {
        Vehiculo::create(['placa' => '1234ABC', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 50, 'activo' => true]);
        Http::fake(['https://inferencia.test/inferir' => Http::response([
            'placa' => '1234-abc', 'confianza' => 0.92, 'categoria' => 'auto', 'ejes' => 2, 'latencia_ms' => 180,
        ])]);

        $this->actingAs($this->operador, 'sanctum')
            ->postJson('/api/v1/pasos/frame', ['imagen' => $this->frame(), 'caseta_id' => $this->caseta->id])
            ->assertCreated()
            ->assertJsonPath('paso.placa', '1234ABC')
            ->assertJsonPath('paso.estado', Paso::COBRADO)
            ->assertJsonPath('paso.monto', 10);

        $this->assertEquals(40, (float) Vehiculo::where('placa', '1234ABC')->value('saldo'));
        $this->assertDatabaseCount('alertas', 0);
    }

    public function test_crea_alerta_si_la_placa_no_esta_registrada(): void
    {
        Http::fake(['https://inferencia.test/inferir' => Http::response([
            'placa' => '9999ZZZ', 'confianza' => 0.8, 'categoria' => 'auto', 'ejes' => 2,
        ])]);

        $this->actingAs($this->operador, 'sanctum')
            ->postJson('/api/v1/pasos/frame', ['imagen' => $this->frame(), 'caseta_id' => $this->caseta->id])
            ->assertCreated()
            ->assertJsonPath('paso.estado', Paso::NO_REGISTRADO);

        $this->assertDatabaseHas('alertas', ['tipo' => Alerta::NO_REGISTRADA, 'atendida' => false]);
    }

    public function test_crea_alerta_si_no_hay_saldo(): void
    {
        Vehiculo::create(['placa' => '5555BBB', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 1, 'activo' => true]);
        Http::fake(['https://inferencia.test/inferir' => Http::response([
            'placa' => '5555BBB', 'confianza' => 0.9, 'categoria' => 'auto', 'ejes' => 2,
        ])]);

        $this->actingAs($this->operador, 'sanctum')
            ->postJson('/api/v1/pasos/frame', ['imagen' => $this->frame(), 'caseta_id' => $this->caseta->id])
            ->assertCreated()
            ->assertJsonPath('paso.estado', Paso::SIN_SALDO);

        $this->assertEquals(1, (float) Vehiculo::where('placa', '5555BBB')->value('saldo'));
        $this->assertDatabaseHas('alertas', ['tipo' => Alerta::SIN_SALDO]);
    }

    public function test_responde_422_si_no_hay_placa_legible(): void
    {
        Http::fake(['https://inferencia.test/inferir' => Http::response(['placa' => null, 'confianza' => null])]);

        $this->actingAs($this->operador, 'sanctum')
            ->postJson('/api/v1/pasos/frame', ['imagen' => $this->frame(), 'caseta_id' => $this->caseta->id])
            ->assertUnprocessable()
            ->assertJsonPath('estado', 'sin_placa');

        $this->assertDatabaseCount('pasos', 0);
    }

    public function test_responde_503_si_la_inferencia_falla(): void
    {
        Http::fake(['https://inferencia.test/inferir' => Http::response('error', 500)]);

        $this->actingAs($this->operador, 'sanctum')
            ->postJson('/api/v1/pasos/frame', ['imagen' => $this->frame(), 'caseta_id' => $this->caseta->id])
            ->assertStatus(503);
    }

    public function test_tarifa_usa_la_mas_cercana_si_no_hay_exacta(): void
    {
        // camion con 7 ejes no tiene tarifa exacta: debe usar la de 6 ejes
        Tarifa::where('categoria', 'camion')->where('ejes', 6)->update(['monto' => 60]);
        Vehiculo::create(['placa' => '7777CCC', 'categoria' => 'camion', 'ejes' => 7, 'saldo' => 500, 'activo' => true]);
        Http::fake(['https://inferencia.test/inferir' => Http::response([
            'placa' => '7777CCC', 'confianza' => 0.9, 'categoria' => 'camion', 'ejes' => 7,
        ])]);

        $this->actingAs($this->operador, 'sanctum')
            ->postJson('/api/v1/pasos/frame', ['imagen' => $this->frame(), 'caseta_id' => $this->caseta->id])
            ->assertCreated()
            ->assertJsonPath('paso.monto', 60);
    }
}
