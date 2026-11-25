<?php

namespace Tests\Feature\Api;

use App\Models\Caseta;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CasetaActivaTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_registra_frames_en_una_caseta_inactiva(): void
    {
        Storage::fake('public');
        config(['peaje.inferencia.url' => 'https://inferencia.test']);
        Http::fake(['https://inferencia.test/inferir' => Http::response(['placa' => 'ABC123'])]);

        $operador = Usuario::create([
            'email' => 'op-caseta@test.com', 'password' => 'password', 'nombre' => 'Op',
            'rol' => Usuario::OPERADOR, 'activo' => true,
        ]);
        $cerrada = Caseta::create(['nombre' => 'Cerrada', 'activa' => false]);

        $this->actingAs($operador, 'sanctum')
            ->postJson('/api/v1/pasos/frame', [
                'imagen' => UploadedFile::fake()->image('f.jpg'),
                'caseta_id' => $cerrada->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('caseta_id');
    }
}
