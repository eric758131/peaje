<?php

namespace Tests\Feature\Web;

use App\Models\Caseta;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cuentas activas, roles en la web y acceso a la API.
 */
class AccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_va_al_login(): void
    {
        $this->get('/panel')->assertRedirect('/login');
    }

    public function test_cuenta_inactiva_no_entra(): void
    {
        $usuario = Usuario::create([
            'email' => 'inactivo@test.com', 'password' => 'password', 'nombre' => 'Inactivo',
            'rol' => Usuario::OPERADOR, 'activo' => false,
        ]);

        $this->actingAs($usuario)->get('/panel')->assertRedirect(route('acceso.denegado'));
    }

    public function test_operador_no_puede_crear_vehiculos(): void
    {
        $operador = Usuario::create([
            'email' => 'op@test.com', 'password' => 'password', 'nombre' => 'Op',
            'rol' => Usuario::OPERADOR, 'activo' => true,
        ]);

        $this->actingAs($operador)->get('/vehiculos/crear')->assertForbidden();
        $this->actingAs($operador)->get('/vehiculos')->assertOk();
    }

    public function test_administrador_puede_crear_vehiculos(): void
    {
        $admin = Usuario::create([
            'email' => 'adm@test.com', 'password' => 'password', 'nombre' => 'Adm',
            'rol' => Usuario::ADMINISTRADOR, 'activo' => true,
        ]);

        $this->actingAs($admin)->get('/vehiculos/crear')->assertOk();
    }

    public function test_la_app_puede_listar_las_casetas_activas(): void
    {
        $operador = Usuario::create([
            'email' => 'casetas@test.com', 'password' => 'password', 'nombre' => 'Op',
            'rol' => Usuario::OPERADOR, 'activo' => true,
        ]);
        Caseta::create(['nombre' => 'Norte', 'activa' => true]);
        Caseta::create(['nombre' => 'Cerrada', 'activa' => false]);

        $this->actingAs($operador, 'sanctum')
            ->getJson('/api/v1/casetas')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.nombre', 'Norte');
    }

    public function test_api_sin_token_responde_401(): void
    {
        $this->getJson('/api/v1/casetas/1/resumen')->assertUnauthorized();
    }

    public function test_api_con_cuenta_inactiva_responde_403(): void
    {
        $usuario = Usuario::create([
            'email' => 'baja@test.com', 'password' => 'password', 'nombre' => 'Baja',
            'rol' => Usuario::ADMINISTRADOR, 'activo' => false,
        ]);

        $this->actingAs($usuario, 'sanctum')->getJson('/api/v1/inferencia/estado')->assertForbidden();
    }
}
