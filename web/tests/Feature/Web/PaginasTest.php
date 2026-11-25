<?php

namespace Tests\Feature\Web;

use App\Models\Caseta;
use App\Models\Paso;
use App\Models\Tarifa;
use App\Models\Usuario;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prueba de humo: todas las páginas responden 200 a un usuario autorizado.
 */
class PaginasTest extends TestCase
{
    use RefreshDatabase;

    public function test_todas_las_paginas_cargan_para_un_operador(): void
    {
        config(['peaje.inferencia.url' => null]);

        $operador = Usuario::create([
            'email' => 'op@test.com', 'password' => 'password', 'nombre' => 'Op',
            'rol' => Usuario::OPERADOR, 'activo' => true,
        ]);

        foreach (['/panel', '/registrar', '/vehiculos', '/pasos', '/alertas', '/alertas?ver=todas', '/chat', '/estado'] as $ruta) {
            $this->actingAs($operador)->get($ruta)->assertOk();
        }
    }

    public function test_paginas_con_datos_y_de_administracion_cargan_para_un_admin(): void
    {
        config(['peaje.inferencia.url' => null]);

        $admin = Usuario::create(['email' => 'admin@test.com', 'password' => 'password', 'nombre' => 'Admin', 'rol' => Usuario::ADMINISTRADOR, 'activo' => true]);
        $caseta = Caseta::create(['nombre' => 'Norte', 'activa' => true]);
        $vehiculo = Vehiculo::create(['placa' => '1234ABC', 'propietario' => 'Ana', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 5, 'activo' => true]);
        $tarifa = Tarifa::create(['categoria' => 'auto', 'ejes' => 2, 'monto' => 10]);
        $paso = Paso::create([
            'placa_leida' => '1234-ABC', 'placa_normalizada' => '1234ABC', 'confianza_ocr' => 97.5, 'categoria_detectada' => 'auto',
            'ejes_detectados' => 2, 'pasajeros' => 1, 'monto' => 10, 'caseta_id' => $caseta->id, 'vehiculo_id' => $vehiculo->id,
            'estado' => Paso::SIN_SALDO, 'imagen_url' => 'https://ejemplo.test/e.jpg',
        ]);
        $paso->alertas()->create(['tipo' => 'sin_saldo', 'atendida' => false]);

        foreach ([
            '/panel', '/registrar', '/pasos', "/pasos/{$paso->id}", '/pasos?placa=1234&estado=sin_saldo', '/alertas',
            '/vehiculos', '/vehiculos?categoria=auto&buscar=ana', "/vehiculos/{$vehiculo->id}", '/vehiculos/crear', "/vehiculos/{$vehiculo->id}/editar",
            '/casetas', '/casetas/crear', "/casetas/{$caseta->id}/editar",
            '/tarifas', '/tarifas/crear', "/tarifas/{$tarifa->id}/editar",
            '/usuarios', '/usuarios/crear', "/usuarios/{$admin->id}/editar",
        ] as $ruta) {
            $this->actingAs($admin)->get($ruta)->assertOk();
        }

        $this->actingAs($admin)->get('/panel')->assertSee('1234ABC')->assertSee('Sin saldo');
    }

    public function test_pagina_de_login_y_acceso_denegado_son_publicas(): void
    {
        $this->get('/login')->assertOk()->assertSee('Contraseña');
        $this->get('/acceso-denegado')->assertOk()->assertSee('Acceso denegado');
    }
}
