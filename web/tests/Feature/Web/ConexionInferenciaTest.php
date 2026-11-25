<?php

namespace Tests\Feature\Web;

use App\Models\Ajuste;
use App\Models\Usuario;
use App\Services\Inferencia\Inferencia;
use App\Services\Inferencia\InferenciaHttp;
use App\Services\Inferencia\InferenciaLocal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Link y token de Colab configurados desde la web (Estado de la IA) en lugar del .env.
 */
class ConexionInferenciaTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['peaje.inferencia.driver' => 'local', 'peaje.inferencia.url' => null, 'peaje.inferencia.token' => null]);
        $this->admin = Usuario::create(['email' => 'admin@test.com', 'password' => 'password', 'nombre' => 'Admin', 'rol' => Usuario::ADMINISTRADOR, 'activo' => true]);
    }

    public function test_el_operador_no_puede_cambiar_la_conexion(): void
    {
        $operador = Usuario::create(['email' => 'op@test.com', 'password' => 'password', 'nombre' => 'Op', 'rol' => Usuario::OPERADOR, 'activo' => true]);

        $this->actingAs($operador)->put('/estado/conexion', ['driver' => 'http', 'url' => 'https://otro.example.com'])->assertForbidden();
        $this->actingAs($operador)->get('/estado')->assertOk()->assertDontSee('Conexión con Colab');
        $this->assertFalse(Ajuste::tiene(Ajuste::INFERENCIA_URL));
    }

    public function test_guarda_el_link_y_el_token_y_la_inferencia_los_usa(): void
    {
        Http::fake(['https://colab.example.com/*' => Http::response(['ok' => true])]);

        $this->actingAs($this->admin)
            ->put('/estado/conexion', ['driver' => 'http', 'url' => ' https://colab.example.com/ ', 'token' => 'secreto-123'])
            ->assertRedirect('/estado')
            ->assertSessionHas('ok');

        // El token se guarda cifrado, nunca en texto plano.
        $this->assertNotSame('secreto-123', DB::table('ajustes')->where('clave', Ajuste::INFERENCIA_TOKEN)->value('valor'));
        $this->assertSame('secreto-123', Ajuste::valor(Ajuste::INFERENCIA_TOKEN));

        $this->assertInstanceOf(InferenciaHttp::class, app(Inferencia::class));
        Http::assertSent(fn (Request $r) => $r->url() === 'https://colab.example.com/health' && $r->header('X-API-Key') === ['secreto-123']);

        // La página muestra el link pero no el token.
        $this->actingAs($this->admin)->get('/estado')->assertOk()
            ->assertSee('Conexión con Colab')->assertSee('https://colab.example.com')->assertDontSee('secreto-123');
    }

    public function test_un_token_vacio_mantiene_el_guardado(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        $this->actingAs($this->admin)->put('/estado/conexion', ['driver' => 'http', 'url' => 'https://uno.example.com', 'token' => 'token-1']);
        $this->actingAs($this->admin)->put('/estado/conexion', ['driver' => 'http', 'url' => 'https://dos.example.com', 'token' => '']);

        $this->assertSame('https://dos.example.com', Ajuste::valor(Ajuste::INFERENCIA_URL));
        $this->assertSame('token-1', Ajuste::valor(Ajuste::INFERENCIA_TOKEN));
    }

    public function test_avisa_si_el_token_es_incorrecto(): void
    {
        // Como en Colab: /health es público y solo /inferir comprueba el token.
        Http::fake([
            'https://colab.example.com/health' => Http::response(['ok' => true]),
            'https://colab.example.com/inferir' => Http::response(['detail' => 'Token inválido.'], 401),
        ]);

        $this->actingAs($this->admin)
            ->put('/estado/conexion', ['driver' => 'http', 'url' => 'https://colab.example.com', 'token' => 'malo'])
            ->assertRedirect('/estado')
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'rechazó el token'));
    }

    public function test_valida_el_link(): void
    {
        $this->actingAs($this->admin)->put('/estado/conexion', ['driver' => 'http', 'url' => ''])->assertSessionHasErrors('url');
        $this->actingAs($this->admin)->put('/estado/conexion', ['driver' => 'http', 'url' => 'no es un link'])->assertSessionHasErrors('url');
        $this->actingAs($this->admin)->put('/estado/conexion', ['driver' => 'otro', 'url' => 'https://a.example.com'])->assertSessionHasErrors('driver');
    }

    public function test_usar_el_env_borra_lo_guardado(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);
        $this->actingAs($this->admin)->put('/estado/conexion', ['driver' => 'http', 'url' => 'https://colab.example.com', 'token' => 't']);

        $this->actingAs($this->admin)->delete('/estado/conexion')->assertRedirect('/estado')->assertSessionHas('ok');

        $this->assertFalse(Ajuste::tiene(Ajuste::INFERENCIA_DRIVER));
        $this->assertNull(Ajuste::valor(Ajuste::INFERENCIA_TOKEN));
        $this->assertInstanceOf(InferenciaLocal::class, app(Inferencia::class));  // vuelve al driver del .env
    }
}
