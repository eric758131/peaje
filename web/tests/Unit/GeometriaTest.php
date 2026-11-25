<?php

namespace Tests\Unit;

use App\Services\Inferencia\Local\Geometria;
use PHPUnit\Framework\TestCase;

class GeometriaTest extends TestCase
{
    private function caja(float $x1, float $y1, float $x2, float $y2, float $score = 0.9, int $clase = 0): array
    {
        return compact('x1', 'y1', 'x2', 'y2', 'score', 'clase');
    }

    public function test_iou(): void
    {
        $this->assertEqualsWithDelta(1.0, Geometria::iou($this->caja(0, 0, 10, 10), $this->caja(0, 0, 10, 10)), 1e-9);
        $this->assertEqualsWithDelta(0.0, Geometria::iou($this->caja(0, 0, 10, 10), $this->caja(20, 20, 30, 30)), 1e-9);
        // Mitad solapada: 50 / 150
        $this->assertEqualsWithDelta(1 / 3, Geometria::iou($this->caja(0, 0, 10, 10), $this->caja(5, 0, 15, 10)), 1e-9);
    }

    public function test_nms_conserva_la_de_mayor_puntaje_por_clase(): void
    {
        $cajas = [
            $this->caja(0, 0, 10, 10, 0.6),
            $this->caja(1, 1, 11, 11, 0.9),
            $this->caja(1, 1, 11, 11, 0.8, clase: 7),   // otra clase: se conserva
            $this->caja(50, 50, 60, 60, 0.5),
        ];

        $resultado = Geometria::nms($cajas, 0.5);

        $this->assertCount(3, $resultado);
        $this->assertSame(0.9, $resultado[0]['score']);
        $this->assertCount(2, Geometria::nms($cajas, 0.5, porClase: false));
    }

    public function test_contiene_centro(): void
    {
        $this->assertTrue(Geometria::contieneCentro($this->caja(0, 0, 100, 100), $this->caja(40, 40, 60, 60)));
        $this->assertFalse(Geometria::contieneCentro($this->caja(0, 0, 100, 100), $this->caja(90, 90, 130, 130)));
    }
}
