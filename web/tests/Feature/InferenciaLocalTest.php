<?php

namespace Tests\Feature;

use App\Services\Inferencia\InferenciaLocal;
use Tests\TestCase;

/**
 * Ejecuta los modelos ONNX reales dentro de PHP.
 * Se salta si este PHP no tiene FFI habilitado. Para correrla:
 *   php -d extension=ffi -d ffi.enable=true artisan test --filter=InferenciaLocalTest
 */
class InferenciaLocalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! InferenciaLocal::ffiPermitidoAqui()) {
            $this->markTestSkipped('PHP sin FFI habilitado.');
        }
    }

    public function test_una_imagen_sin_vehiculo_devuelve_el_contrato_completo(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'peaje').'.jpg';
        $imagen = imagecreatetruecolor(640, 480);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, 200, 210, 220));
        imagejpeg($imagen, $ruta);

        $local = new InferenciaLocal(config('peaje.inferencia.local'));
        $resultado = $local->enEsteProceso($ruta);
        @unlink($ruta);

        foreach (['placa', 'confianza', 'categoria', 'ejes', 'pasajeros', 'latencia_ms', 'evidencia', 'detalle'] as $clave) {
            $this->assertArrayHasKey($clave, $resultado);
        }
        $this->assertNull($resultado['placa']);
        $this->assertNull($resultado['categoria']);
        $this->assertNotFalse(base64_decode($resultado['evidencia'], true));
        $this->assertArrayHasKey('m2_segmentacion', $resultado['detalle']['tiempos_ms']);
    }

    public function test_el_diagnostico_encuentra_modelos_y_libreria(): void
    {
        $estado = (new InferenciaLocal(config('peaje.inferencia.local')))->estaDisponible();

        $this->assertTrue($estado['disponible'], $estado['detalle']);
        $this->assertSame('local', $estado['driver']);
    }
}
