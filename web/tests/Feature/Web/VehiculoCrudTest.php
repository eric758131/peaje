<?php

namespace Tests\Feature\Web;

use App\Models\Usuario;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehiculoCrudTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Usuario::create([
            'email' => 'admin-crud@test.com', 'password' => 'password', 'nombre' => 'Admin',
            'rol' => Usuario::ADMINISTRADOR, 'activo' => true,
        ]);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'placa' => '1-234 abc',
            'propietario' => 'Juan Pérez',
            'categoria' => 'auto',
            'ejes' => 2,
            'saldo' => 50,
            'activo' => '1',
        ], $extra);
    }

    public function test_admin_crea_vehiculo_con_placa_normalizada(): void
    {
        $this->actingAs($this->admin)->post('/vehiculos', $this->datos())
            ->assertRedirect(route('vehiculos.show', Vehiculo::where('placa', '1234ABC')->value('id')));

        $this->assertDatabaseHas('vehiculos', ['placa' => '1234ABC', 'categoria' => 'auto', 'activo' => true]);
    }

    public function test_no_permite_placa_duplicada(): void
    {
        Vehiculo::create(['placa' => '1234ABC', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 0, 'activo' => true]);

        $this->actingAs($this->admin)
            ->post('/vehiculos', $this->datos())
            ->assertSessionHasErrors('placa');

        $this->assertDatabaseCount('vehiculos', 1);
    }

    public function test_admin_edita_vehiculo_sin_chocar_con_su_propia_placa(): void
    {
        $vehiculo = Vehiculo::create(['placa' => '1234ABC', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 0, 'activo' => true]);

        $this->actingAs($this->admin)
            ->put("/vehiculos/{$vehiculo->id}", $this->datos(['saldo' => 99]))
            ->assertRedirect(route('vehiculos.show', $vehiculo));

        $this->assertEquals(99, (float) $vehiculo->fresh()->saldo);
    }

    public function test_checkbox_desmarcado_desactiva_el_vehiculo(): void
    {
        $vehiculo = Vehiculo::create(['placa' => '1234ABC', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 0, 'activo' => true]);

        $datos = $this->datos();
        unset($datos['activo']);

        $this->actingAs($this->admin)->put("/vehiculos/{$vehiculo->id}", $datos);

        $this->assertFalse($vehiculo->fresh()->activo);
    }

    public function test_admin_elimina_vehiculo(): void
    {
        $vehiculo = Vehiculo::create(['placa' => '1234ABC', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 0, 'activo' => true]);

        $this->actingAs($this->admin)->delete("/vehiculos/{$vehiculo->id}")->assertRedirect('/vehiculos');

        $this->assertDatabaseMissing('vehiculos', ['id' => $vehiculo->id]);
    }

    public function test_guarda_la_imagen_inferida_y_la_muestra_en_ver_y_editar(): void
    {
        Storage::fake('public');
        $jpeg = function (int $color): string {
            $img = imagecreatetruecolor(40, 30);
            imagefill($img, 0, 0, $color);
            ob_start();
            imagejpeg($img);

            return 'data:image/jpeg;base64,'.base64_encode((string) ob_get_clean());
        };

        $this->actingAs($this->admin)->post('/vehiculos', $this->datos(['foto_inferida' => $jpeg(0xFF0000)]));
        $vehiculo = Vehiculo::where('placa', '1234ABC')->firstOrFail();
        Storage::disk('public')->assertExists($vehiculo->foto);

        $this->actingAs($this->admin)->get("/vehiculos/{$vehiculo->id}")->assertOk()->assertSee($vehiculo->fotoUrl(), false)->assertSee('Imagen inferida');
        $this->actingAs($this->admin)->get("/vehiculos/{$vehiculo->id}/editar")->assertOk()->assertSee($vehiculo->fotoUrl(), false);

        // Editar sin foto nueva conserva la anterior; con foto nueva la reemplaza y borra la vieja.
        $anterior = $vehiculo->foto;
        $this->actingAs($this->admin)->put("/vehiculos/{$vehiculo->id}", $this->datos());
        $this->assertSame($anterior, $vehiculo->fresh()->foto);

        $this->actingAs($this->admin)->put("/vehiculos/{$vehiculo->id}", $this->datos(['foto_inferida' => $jpeg(0x00FF00)]));
        $this->assertNotSame($anterior, $vehiculo->fresh()->foto);
        Storage::disk('public')->assertMissing($anterior);
    }

    public function test_rechaza_una_imagen_inferida_que_no_es_jpeg(): void
    {
        $this->actingAs($this->admin)->post('/vehiculos', $this->datos(['foto_inferida' => 'data:text/html;base64,PGgxPg==']))
            ->assertSessionHasErrors('foto_inferida');
    }
}
