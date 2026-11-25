<?php

namespace Tests\Feature\Api;

use App\Models\Caseta;
use App\Models\Paso;
use App\Models\Usuario;
use App\Models\Vehiculo;
use Database\Seeders\TarifaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Un video envía muchos frames del mismo vehículo: solo el primero cobra.
 */
class FrameDuplicadoTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $operador;

    private Caseta $caseta;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['peaje.inferencia.url' => 'https://inferencia.test', 'peaje.ventana_duplicado' => 60]);

        $this->seed(TarifaSeeder::class);
        $this->operador = Usuario::create(['email' => 'op@test.com', 'password' => 'password', 'nombre' => 'Op', 'rol' => Usuario::OPERADOR, 'activo' => true]);
        $this->caseta = Caseta::create(['nombre' => 'Norte', 'activa' => true]);
    }

    private function enviar(string $placa)
    {
        Http::fake(['https://inferencia.test/inferir' => Http::response([
            'placa' => $placa, 'confianza' => 0.95, 'categoria' => 'auto', 'ejes' => 2, 'pasajeros' => 1,
            'evidencia' => base64_encode('jpeg-falso'),
        ])]);

        return $this->actingAs($this->operador, 'sanctum')
            ->postJson('/api/v1/pasos/frame', ['imagen' => UploadedFile::fake()->image('f.jpg'), 'caseta_id' => $this->caseta->id]);
    }

    public function test_el_segundo_frame_de_la_misma_placa_no_cobra(): void
    {
        Vehiculo::create(['placa' => '1234ABC', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 50, 'activo' => true]);

        $this->enviar('1234ABC')->assertCreated()->assertJsonPath('duplicado', false);
        $this->enviar('1234-abc')->assertOk()->assertJsonPath('duplicado', true);

        $this->assertDatabaseCount('pasos', 1);
        $this->assertEquals(40, (float) Vehiculo::where('placa', '1234ABC')->value('saldo'));
    }

    public function test_una_variante_de_ocr_tambien_cuenta_como_duplicado(): void
    {
        $this->enviar('1234ABC')->assertCreated();
        $this->enviar('1234A8C')->assertOk()->assertJsonPath('duplicado', true);

        $this->assertDatabaseCount('pasos', 1);
    }

    public function test_fuera_de_la_ventana_se_registra_otro_paso(): void
    {
        $this->enviar('1234ABC')->assertCreated();
        Paso::query()->update(['created_at' => now()->subMinutes(5)]);

        $this->enviar('1234ABC')->assertCreated();
        $this->assertDatabaseCount('pasos', 2);
    }

    public function test_una_lectura_incompleta_no_registra_paso(): void
    {
        $this->enviar('12A')->assertUnprocessable()->assertJsonPath('estado', 'sin_placa');
        $this->assertDatabaseCount('pasos', 0);
    }

    public function test_guarda_la_evidencia_anotada_y_los_pasajeros(): void
    {
        $this->enviar('9999ZZZ')->assertCreated()->assertJsonPath('paso.pasajeros', 1);

        $paso = Paso::first();
        $this->assertStringContainsString('/storage/evidencias/', $paso->imagen_url);
        $ruta = substr($paso->imagen_url, strpos($paso->imagen_url, 'evidencias/'));
        Storage::disk('public')->assertExists($ruta);
        $this->assertSame('jpeg-falso', Storage::disk('public')->get($ruta));
    }
}
