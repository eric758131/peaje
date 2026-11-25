<?php

namespace Tests\Feature\Api;

use App\Models\Caseta;
use App\Models\Usuario;
use App\Models\Vehiculo;
use Database\Seeders\TarifaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lecturas reales que fallaron al probar con video: basura del OCR, una letra leída
 * como dígito y una placa sin vehículo visible.
 */
class LecturaDudosaTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $operador;

    private Caseta $caseta;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['peaje.inferencia.url' => 'https://inferencia.test', 'peaje.confianza_minima_ocr' => 0.6]);

        $this->seed(TarifaSeeder::class);
        $this->operador = Usuario::create(['email' => 'op@test.com', 'password' => 'password', 'nombre' => 'Op', 'rol' => Usuario::OPERADOR, 'activo' => true]);
        $this->caseta = Caseta::create(['nombre' => 'Norte', 'activa' => true]);
    }

    private function enviar(string $placa, float $confianza, ?string $categoria = 'minibus', ?int $ejes = 2)
    {
        Http::fake(['https://inferencia.test/inferir' => Http::response([
            'placa' => $placa, 'confianza' => $confianza, 'categoria' => $categoria, 'ejes' => $ejes, 'pasajeros' => null,
        ])]);

        return $this->actingAs($this->operador, 'sanctum')
            ->postJson('/api/v1/pasos/frame', ['imagen' => UploadedFile::fake()->image('f.jpg'), 'caseta_id' => $this->caseta->id]);
    }

    public function test_lectura_sin_formato_y_poca_confianza_no_registra_ni_alerta(): void
    {
        $this->enviar('40L1M0L', 0.45)
            ->assertUnprocessable()
            ->assertJsonPath('estado', 'sin_placa')
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Lectura dudosa (40L1M0L, 45 %)'));

        $this->assertDatabaseCount('pasos', 0);
        $this->assertDatabaseCount('alertas', 0);
    }

    public function test_una_letra_leida_como_digito_se_corrige_y_cobra_al_vehiculo_correcto(): void
    {
        Vehiculo::create(['placa' => '242AYJ', 'categoria' => 'minibus', 'ejes' => 2, 'saldo' => 50, 'activo' => true]);

        $this->enviar('2424YJ', 0.87)->assertCreated()->assertJsonPath('paso.placa', '242AYJ')->assertJsonPath('paso.estado', 'cobrado');

        $this->assertDatabaseHas('pasos', ['placa_leida' => '2424YJ', 'placa_normalizada' => '242AYJ']);
        $this->assertDatabaseCount('alertas', 0);
    }

    public function test_lectura_cortada_con_confianza_media_no_registra(): void
    {
        // Caso real (Colab): la placa 477AAL leída como 7AAAL al 77 %.
        $this->enviar('7AAAL', 0.77)->assertUnprocessable()->assertJsonPath('estado', 'sin_placa');
        $this->assertDatabaseCount('pasos', 0);
    }

    public function test_placa_extranjera_legible_si_se_registra(): void
    {
        $this->enviar('ABC1234', 0.95)->assertCreated()->assertJsonPath('paso.estado', 'no_registrado');
    }

    public function test_sin_vehiculo_visible_no_inventa_categoria_ni_monto(): void
    {
        $this->enviar('1852PHD', 0.99, categoria: null, ejes: null)
            ->assertCreated()
            ->assertJsonPath('paso.categoria', null)
            ->assertJsonPath('paso.ejes', null)
            ->assertJsonPath('paso.monto', 0);
    }

    public function test_sin_vehiculo_visible_usa_los_datos_del_vehiculo_registrado(): void
    {
        Vehiculo::create(['placa' => '1852PHD', 'categoria' => 'camion', 'ejes' => 3, 'saldo' => 500, 'activo' => true]);

        $this->enviar('1852PHD', 0.99, categoria: null, ejes: null)
            ->assertCreated()
            ->assertJsonPath('paso.categoria', 'camion')
            ->assertJsonPath('paso.ejes', 3)
            ->assertJsonPath('paso.estado', 'cobrado');
    }
}
