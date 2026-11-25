<?php

namespace Tests\Feature\Api;

use App\Models\Caseta;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acceso a la API: clave de la app móvil (X-Api-Key) o token de /auth/login.
 */
class AccesoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['peaje.api_key' => 'clave-de-prueba']);
        Caseta::create(['nombre' => 'Norte', 'activa' => true]);
    }

    public function test_la_app_entra_con_la_clave(): void
    {
        $this->getJson('/api/v1/casetas', ['X-Api-Key' => 'clave-de-prueba'])->assertOk()->assertJsonCount(1);
    }

    public function test_clave_incorrecta_o_ausente_responde_401(): void
    {
        $this->getJson('/api/v1/casetas', ['X-Api-Key' => 'otra'])->assertUnauthorized();
        $this->getJson('/api/v1/casetas')->assertUnauthorized();
    }

    public function test_sin_clave_configurada_la_cabecera_no_sirve(): void
    {
        config(['peaje.api_key' => null]);

        $this->getJson('/api/v1/casetas', ['X-Api-Key' => ''])->assertUnauthorized();
    }

    public function test_login_devuelve_un_token_que_sirve_para_la_api(): void
    {
        Usuario::create(['email' => 'op@test.com', 'password' => 'secreta123', 'nombre' => 'Op', 'rol' => Usuario::OPERADOR, 'activo' => true]);

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'op@test.com', 'password' => 'secreta123'])
            ->assertOk()
            ->assertJsonPath('usuario.email', 'op@test.com')
            ->json('token');

        $this->getJson('/api/v1/casetas', ['Authorization' => 'Bearer '.$token])->assertOk();
    }

    public function test_login_rechaza_contrasena_incorrecta_y_cuenta_inactiva(): void
    {
        Usuario::create(['email' => 'op@test.com', 'password' => 'secreta123', 'nombre' => 'Op', 'rol' => Usuario::OPERADOR, 'activo' => true]);
        Usuario::create(['email' => 'baja@test.com', 'password' => 'secreta123', 'nombre' => 'Baja', 'rol' => Usuario::OPERADOR, 'activo' => false]);

        $this->postJson('/api/v1/auth/login', ['email' => 'op@test.com', 'password' => 'mal'])->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => 'baja@test.com', 'password' => 'secreta123'])->assertForbidden();
    }
}
