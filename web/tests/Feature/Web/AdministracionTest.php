<?php

namespace Tests\Feature\Web;

use App\Models\Caseta;
use App\Models\Paso;
use App\Models\Tarifa;
use App\Models\Usuario;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * CRUD de casetas, tarifas y usuarios, y anulación de pasos.
 */
class AdministracionTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    private Usuario $operador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Usuario::create(['email' => 'admin@test.com', 'password' => 'password', 'nombre' => 'Admin', 'rol' => Usuario::ADMINISTRADOR, 'activo' => true]);
        $this->operador = Usuario::create(['email' => 'op@test.com', 'password' => 'password', 'nombre' => 'Op', 'rol' => Usuario::OPERADOR, 'activo' => true]);
    }

    public function test_operador_no_entra_a_la_administracion(): void
    {
        foreach (['/casetas', '/tarifas', '/usuarios', '/casetas/crear', '/tarifas/crear', '/usuarios/crear'] as $ruta) {
            $this->actingAs($this->operador)->get($ruta)->assertForbidden();
        }
    }

    public function test_crud_de_casetas(): void
    {
        $this->actingAs($this->admin)->post('/casetas', ['nombre' => 'Caseta Este', 'ubicacion' => 'Km 5', 'activa' => '1'])
            ->assertRedirect('/casetas');
        $caseta = Caseta::where('nombre', 'Caseta Este')->firstOrFail();
        $this->assertTrue($caseta->activa);

        $this->actingAs($this->admin)->post('/casetas', ['nombre' => 'Caseta Este'])->assertSessionHasErrors('nombre');

        $this->actingAs($this->admin)->put("/casetas/{$caseta->id}", ['nombre' => 'Caseta Este', 'activa' => '0'])->assertRedirect('/casetas');
        $this->assertFalse($caseta->fresh()->activa);

        $this->actingAs($this->admin)->delete("/casetas/{$caseta->id}")->assertRedirect('/casetas');
        $this->assertModelMissing($caseta);
    }

    public function test_no_elimina_una_caseta_con_pasos(): void
    {
        $caseta = Caseta::create(['nombre' => 'Norte', 'activa' => true]);
        Paso::create(['placa_normalizada' => '1234ABC', 'caseta_id' => $caseta->id, 'estado' => Paso::NO_REGISTRADO, 'monto' => 10]);

        $this->actingAs($this->admin)->delete("/casetas/{$caseta->id}")->assertSessionHas('error');
        $this->assertModelExists($caseta);
    }

    public function test_crud_de_tarifas_con_par_unico(): void
    {
        $this->actingAs($this->admin)->post('/tarifas', ['categoria' => 'bus', 'ejes' => 3, 'monto' => 30])->assertRedirect('/tarifas');
        $this->actingAs($this->admin)->post('/tarifas', ['categoria' => 'bus', 'ejes' => 3, 'monto' => 99])->assertSessionHasErrors('ejes');

        $tarifa = Tarifa::where('categoria', 'bus')->where('ejes', 3)->firstOrFail();
        $this->actingAs($this->admin)->put("/tarifas/{$tarifa->id}", ['categoria' => 'bus', 'ejes' => 3, 'monto' => 35])->assertRedirect('/tarifas');
        $this->assertEquals(35, (float) $tarifa->fresh()->monto);

        $this->actingAs($this->admin)->delete("/tarifas/{$tarifa->id}");
        $this->assertModelMissing($tarifa);
    }

    public function test_usuarios_normaliza_el_correo_cifra_la_contrasena_y_protege_al_propio_admin(): void
    {
        $this->actingAs($this->admin)->post('/usuarios', [
            'email' => '  Nuevo@Gmail.com ', 'password' => 'secreta123', 'password_confirmation' => 'secreta123',
            'nombre' => 'Nuevo', 'rol' => 'operador', 'activo' => '1',
        ])->assertRedirect('/usuarios');

        $nuevo = Usuario::where('email', 'nuevo@gmail.com')->firstOrFail();
        $this->assertSame('operador', $nuevo->rol);
        $this->assertNotSame('secreta123', $nuevo->password);
        $this->assertTrue(Hash::check('secreta123', $nuevo->password));

        // Sin contraseña al crear: se acepta, la cuenta solo entra con Google.
        $this->actingAs($this->admin)->post('/usuarios', ['email' => 'otro@test.com', 'nombre' => 'Otro', 'rol' => 'operador', 'activo' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertNull(Usuario::where('email', 'otro@test.com')->value('password'));

        // Contraseña corta: no se acepta.
        $this->actingAs($this->admin)->post('/usuarios', ['email' => 'otro2@test.com', 'password' => 'corta', 'password_confirmation' => 'corta', 'nombre' => 'Otro', 'rol' => 'operador', 'activo' => '1'])
            ->assertSessionHasErrors('password');

        // Al editar, la contraseña vacía se mantiene.
        $this->actingAs($this->admin)->put("/usuarios/{$nuevo->id}", ['email' => 'nuevo@gmail.com', 'password' => '', 'nombre' => 'Nuevo 2', 'rol' => 'operador', 'activo' => '1'])
            ->assertRedirect('/usuarios');
        $this->assertTrue(Hash::check('secreta123', $nuevo->fresh()->password));

        $this->actingAs($this->admin)->put("/usuarios/{$this->admin->id}", ['email' => 'admin@test.com', 'nombre' => 'Admin', 'rol' => 'operador', 'activo' => '1'])
            ->assertSessionHas('error');
        $this->assertSame(Usuario::ADMINISTRADOR, $this->admin->fresh()->rol);

        $this->actingAs($this->admin)->delete("/usuarios/{$this->admin->id}")->assertSessionHas('error');
        $this->assertModelExists($this->admin);
    }

    public function test_desactivar_una_cuenta_revoca_sus_tokens(): void
    {
        $this->operador->createToken('movil');

        $this->actingAs($this->admin)->put("/usuarios/{$this->operador->id}", ['email' => 'op@test.com', 'nombre' => 'Op', 'rol' => 'operador', 'activo' => '0']);

        $this->assertFalse($this->operador->fresh()->activo);
        $this->assertSame(0, $this->operador->tokens()->count());
    }

    public function test_anular_un_paso_cobrado_devuelve_el_saldo(): void
    {
        $caseta = Caseta::create(['nombre' => 'Norte', 'activa' => true]);
        $vehiculo = Vehiculo::create(['placa' => '1234ABC', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 40, 'activo' => true]);
        $paso = Paso::create(['placa_normalizada' => '1234ABC', 'caseta_id' => $caseta->id, 'vehiculo_id' => $vehiculo->id, 'estado' => Paso::COBRADO, 'monto' => 10]);

        $this->actingAs($this->operador)->delete("/pasos/{$paso->id}")->assertForbidden();

        $this->actingAs($this->admin)->delete("/pasos/{$paso->id}")->assertRedirect('/pasos');
        $this->assertModelMissing($paso);
        $this->assertEquals(50, (float) $vehiculo->fresh()->saldo);
    }
}
