<?php

namespace Tests\Feature\Web;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as UsuarioGoogle;
use Mockery;
use Tests\TestCase;

/**
 * Inicio de sesión con Google (web: OAuth 2.0 con Socialite; API: ID token en /api/v1/auth/google).
 */
class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'cliente-web.apps.googleusercontent.com',
            'services.google.client_secret' => 'secreto',
            'services.google.client_ids_extra' => ['cliente-android.apps.googleusercontent.com'],
        ]);
    }

    private function googleDevuelve(string $email, bool $verificado = true): void
    {
        $cuenta = (new UsuarioGoogle)->setRaw(['email' => $email, 'email_verified' => $verificado])
            ->map(['id' => '123', 'email' => $email, 'name' => 'Nombre Google']);

        $proveedor = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
        $proveedor->shouldReceive('user')->andReturn($cuenta);
        Socialite::shouldReceive('driver')->with('google')->andReturn($proveedor);
    }

    public function test_la_pagina_de_login_muestra_el_boton_de_google(): void
    {
        $this->get('/login')->assertOk()->assertSee('Continuar con Google')->assertSee('Contraseña');

        config(['peaje.login_con_contrasena' => false]);
        $this->get('/login')->assertOk()->assertSee('Continuar con Google')->assertDontSee('name="password"', false);
        $this->post('/login', ['email' => 'a@b.com', 'password' => 'x'])->assertNotFound();
    }

    public function test_sin_configurar_google_el_boton_explica_que_falta(): void
    {
        config(['services.google.client_id' => null]);

        $this->get('/login')->assertOk()->assertSee('Continuar con Google');
        $this->get('/auth/google')->assertRedirect('/login');
        $this->get('/login')->assertSee('todavía no está configurado');
    }

    public function test_redirige_a_google(): void
    {
        $respuesta = $this->get('/auth/google');

        $respuesta->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth', $respuesta->headers->get('Location'));
        $this->assertStringContainsString('auth%2Fgoogle%2Fcallback', $respuesta->headers->get('Location'));
    }

    public function test_cuenta_autorizada_entra_con_google(): void
    {
        $usuario = Usuario::create(['email' => 'docente@unifranz.edu.bo', 'nombre' => 'docente@unifranz.edu.bo', 'rol' => Usuario::ADMINISTRADOR, 'activo' => true]);
        $this->googleDevuelve('Docente@Unifranz.edu.bo');

        $this->get('/auth/google/callback?code=abc&state=x')->assertRedirect('/panel');

        $this->assertAuthenticatedAs($usuario);
        $this->assertSame('Nombre Google', $usuario->fresh()->nombre);  // toma el nombre de Google si no tenía uno
    }

    public function test_cuenta_no_autorizada_ve_acceso_denegado(): void
    {
        $this->googleDevuelve('intruso@gmail.com');

        $this->get('/auth/google/callback?code=abc&state=x')->assertRedirect('/acceso-denegado');
        $this->assertGuest();
        $this->get('/acceso-denegado')->assertSee('intruso@gmail.com')->assertSee('no está autorizada');
    }

    public function test_cuenta_desactivada_ve_acceso_denegado(): void
    {
        Usuario::create(['email' => 'baja@gmail.com', 'nombre' => 'Baja', 'rol' => Usuario::OPERADOR, 'activo' => false]);
        $this->googleDevuelve('baja@gmail.com');

        $this->get('/auth/google/callback?code=abc&state=x')->assertRedirect('/acceso-denegado');
        $this->assertGuest();
    }

    public function test_si_el_usuario_cancela_en_google_vuelve_al_login(): void
    {
        $this->get('/auth/google/callback?error=access_denied')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_una_cuenta_sin_contrasena_no_entra_con_contrasena(): void
    {
        Usuario::create(['email' => 'solo-google@gmail.com', 'nombre' => 'G', 'rol' => Usuario::OPERADOR, 'activo' => true]);

        $this->post('/login', ['email' => 'solo-google@gmail.com', 'password' => 'cualquiera'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_api_auth_google_devuelve_token_para_cuenta_autorizada(): void
    {
        Usuario::create(['email' => 'op@gmail.com', 'nombre' => 'Op', 'rol' => Usuario::OPERADOR, 'activo' => true]);
        Http::fake(['oauth2.googleapis.com/*' => Http::response([
            'iss' => 'https://accounts.google.com', 'aud' => 'cliente-android.apps.googleusercontent.com',
            'exp' => (string) (time() + 3600), 'email' => 'op@gmail.com', 'email_verified' => 'true', 'name' => 'Op',
        ])]);

        $token = $this->postJson('/api/v1/auth/google', ['id_token' => 'token-de-google'])
            ->assertOk()->assertJsonPath('usuario.rol', 'operador')->json('token');

        // El token propio sirve para el resto de la API.
        $this->withToken($token)->getJson('/api/v1/casetas')->assertOk();
    }

    public function test_api_auth_google_rechaza_tokens_invalidos_y_cuentas_no_autorizadas(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::sequence()
            ->push(['error' => 'invalid_token'], 400)
            ->push(['iss' => 'accounts.google.com', 'aud' => 'otro-cliente', 'exp' => (string) (time() + 3600), 'email' => 'x@gmail.com', 'email_verified' => 'true'])
            ->push(['iss' => 'accounts.google.com', 'aud' => 'cliente-web.apps.googleusercontent.com', 'exp' => (string) (time() + 3600), 'email' => 'nadie@gmail.com', 'email_verified' => 'true']),
        ]);

        $this->postJson('/api/v1/auth/google', ['id_token' => 'malo'])->assertUnauthorized();
        $this->postJson('/api/v1/auth/google', ['id_token' => 'de-otra-app'])->assertUnauthorized();
        $this->postJson('/api/v1/auth/google', ['id_token' => 'no-autorizado'])->assertForbidden();
        $this->postJson('/api/v1/auth/google', [])->assertUnprocessable();
    }
}
