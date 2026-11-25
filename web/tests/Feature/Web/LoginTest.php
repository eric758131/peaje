<?php

namespace Tests\Feature\Web;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inicio de sesión web con email y contraseña.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function cuenta(bool $activo = true): Usuario
    {
        return Usuario::create([
            'email' => 'op@test.com', 'password' => 'secreta123', 'nombre' => 'Op',
            'rol' => Usuario::OPERADOR, 'activo' => $activo,
        ]);
    }

    public function test_credenciales_correctas_entran_al_panel(): void
    {
        $usuario = $this->cuenta();

        $this->post('/login', ['email' => ' OP@test.com ', 'password' => 'secreta123'])->assertRedirect(route('panel'));
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_contrasena_incorrecta_no_entra(): void
    {
        $this->cuenta();

        $this->from('/login')->post('/login', ['email' => 'op@test.com', 'password' => 'otra'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_cuenta_inactiva_no_entra_aunque_la_contrasena_sea_correcta(): void
    {
        $this->cuenta(activo: false);

        $this->post('/login', ['email' => 'op@test.com', 'password' => 'secreta123'])->assertRedirect(route('acceso.denegado'));
        $this->assertGuest();
    }

    public function test_bloquea_tras_cinco_intentos_fallidos(): void
    {
        $this->cuenta();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'op@test.com', 'password' => 'mal']);
        }

        $this->post('/login', ['email' => 'op@test.com', 'password' => 'secreta123'])->assertSessionHasErrors('email');
        $this->assertStringStartsWith('Demasiados intentos', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_cerrar_sesion(): void
    {
        $this->actingAs($this->cuenta())->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
