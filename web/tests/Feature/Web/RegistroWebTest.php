<?php

namespace Tests\Feature\Web;

use App\Models\Caseta;
use App\Models\Usuario;
use Database\Seeders\TarifaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Registrar paso desde el navegador y probar la inferencia.
 */
class RegistroWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['peaje.inferencia.url' => 'https://inferencia.test']);
        $this->seed(TarifaSeeder::class);
    }

    public function test_un_frame_del_navegador_registra_el_paso_y_devuelve_la_evidencia(): void
    {
        $operador = Usuario::create(['email' => 'op@test.com', 'password' => 'password', 'nombre' => 'Op', 'rol' => Usuario::OPERADOR, 'activo' => true]);
        $caseta = Caseta::create(['nombre' => 'Norte', 'activa' => true]);

        Http::fake(['https://inferencia.test/inferir' => Http::response([
            'placa' => '4321XYZ', 'confianza' => 0.9, 'categoria' => 'minibus', 'ejes' => 2, 'pasajeros' => 3,
            'evidencia' => base64_encode('jpeg'), 'detalle' => ['llantas' => [[10, 20], [80, 20]]],
        ])]);

        $this->actingAs($operador)
            ->postJson('/registrar/frame', ['imagen' => UploadedFile::fake()->image('frame.jpg'), 'caseta_id' => $caseta->id])
            ->assertOk()
            ->assertJsonPath('resultado', 'registrado')
            ->assertJsonPath('paso.placa', '4321XYZ')
            ->assertJsonPath('paso.estado', 'no_registrado')
            ->assertJsonPath('paso.pasajeros', 3)
            ->assertJsonPath('paso.monto', 15)
            ->assertJsonPath('evidencia', 'data:image/jpeg;base64,'.base64_encode('jpeg'));

        $this->assertDatabaseHas('alertas', ['tipo' => 'no_registrada']);
    }

    public function test_probar_inferencia_no_registra_pasos(): void
    {
        $operador = Usuario::create(['email' => 'op@test.com', 'password' => 'password', 'nombre' => 'Op', 'rol' => Usuario::OPERADOR, 'activo' => true]);
        Http::fake(['https://inferencia.test/inferir' => Http::response(['placa' => '1111AAA', 'categoria' => 'auto', 'ejes' => 2])]);

        $this->actingAs($operador)
            ->postJson('/inferencia/probar', ['imagen' => UploadedFile::fake()->image('a.jpg')])
            ->assertOk()
            ->assertJsonPath('lectura.placa', '1111AAA');

        $this->assertDatabaseCount('pasos', 0);
    }

    public function test_si_la_inferencia_falla_responde_503(): void
    {
        $operador = Usuario::create(['email' => 'op@test.com', 'password' => 'password', 'nombre' => 'Op', 'rol' => Usuario::OPERADOR, 'activo' => true]);
        $caseta = Caseta::create(['nombre' => 'Norte', 'activa' => true]);
        Http::fake(['https://inferencia.test/inferir' => Http::response('caído', 500)]);

        $this->actingAs($operador)
            ->postJson('/registrar/frame', ['imagen' => UploadedFile::fake()->image('frame.jpg'), 'caseta_id' => $caseta->id])
            ->assertStatus(503);
    }
}
