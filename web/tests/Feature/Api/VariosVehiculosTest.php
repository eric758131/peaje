<?php

namespace Tests\Feature\Api;

use App\Models\Caseta;
use App\Models\Usuario;
use App\Models\Vehiculo;
use Database\Seeders\TarifaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Un frame con varios vehículos (el endpoint de Colab devuelve `vehiculos`): un paso por cada placa.
 */
class VariosVehiculosTest extends TestCase
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
        $this->operador = Usuario::create(['email' => 'op@test.com', 'password' => 'password', 'nombre' => 'Op', 'rol' => Usuario::OPERADOR, 'activo' => true]);
        $this->caseta = Caseta::create(['nombre' => 'Norte', 'activa' => true]);
    }

    private function enviar()
    {
        Http::fake(['https://inferencia.test/inferir' => Http::response([
            'placa' => '1374LYG', 'confianza' => 0.91, 'categoria' => 'auto', 'ejes' => 2, 'pasajeros' => 0,
            'evidencia' => base64_encode('jpeg-falso'),
            'vehiculos' => [
                ['categoria' => 'auto', 'placa' => '1374LYG', 'confianza_placa' => 0.91, 'ejes' => 2, 'pasajeros' => 0, 'principal' => true],
                ['categoria' => 'bus', 'placa' => '4027UGL', 'confianza_placa' => 0.99, 'ejes' => 2, 'pasajeros' => 3],
                // Lejano, OCR poco seguro (lectura errada de otro auto): se ve, pero no genera paso.
                ['categoria' => 'auto', 'placa' => '1321YYG', 'confianza_placa' => 0.8, 'ejes' => 2, 'pasajeros' => 0],
                ['categoria' => 'auto', 'placa' => 'T7777', 'confianza_placa' => 0.55, 'ejes' => 2, 'pasajeros' => 0],
                ['categoria' => 'camion', 'placa' => null, 'ejes' => 3, 'pasajeros' => 0],
            ],
        ])]);

        return $this->actingAs($this->operador, 'sanctum')
            ->postJson('/api/v1/pasos/frame', ['imagen' => UploadedFile::fake()->image('f.jpg'), 'caseta_id' => $this->caseta->id]);
    }

    public function test_registra_un_paso_por_cada_vehiculo_con_placa_valida(): void
    {
        Vehiculo::create(['placa' => '4027UGL', 'categoria' => 'bus', 'ejes' => 2, 'saldo' => 100, 'activo' => true]);

        $this->enviar()->assertCreated()->assertJsonCount(2, 'pasos');

        // La lectura dudosa (T7777), el lejano poco seguro (1321YYG) y el camión sin placa no crean pasos.
        $this->assertDatabaseCount('pasos', 2);
        $this->assertDatabaseHas('pasos', ['placa_normalizada' => '4027UGL', 'estado' => 'cobrado', 'pasajeros' => 3]);
        $this->assertDatabaseHas('pasos', ['placa_normalizada' => '1374LYG', 'estado' => 'no_registrado']);

        // Una sola imagen de evidencia para los dos pasos.
        $this->assertCount(1, Storage::disk('public')->allFiles('evidencias'));
    }

    public function test_el_siguiente_frame_no_cobra_de_nuevo_a_ninguno(): void
    {
        $this->enviar()->assertCreated();
        $this->enviar()->assertOk()->assertJsonPath('duplicado', true);

        $this->assertDatabaseCount('pasos', 2);
    }

    public function test_reintenta_si_el_tunel_corta_la_conexion(): void
    {
        $intentos = 0;
        Http::fake(function () use (&$intentos) {
            if (++$intentos === 1) {
                throw new ConnectionException('cURL error 52: Empty reply from server');
            }

            return Http::response(['placa' => '1234ABC', 'confianza' => 0.95, 'categoria' => 'auto', 'ejes' => 2]);
        });

        $this->actingAs($this->operador, 'sanctum')
            ->postJson('/api/v1/pasos/frame', ['imagen' => UploadedFile::fake()->image('f.jpg'), 'caseta_id' => $this->caseta->id])
            ->assertCreated();

        $this->assertSame(2, $intentos);
    }
}
