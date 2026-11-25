<?php

namespace Tests\Unit;

use App\Support\Placa;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlacaTest extends TestCase
{
    #[DataProvider('normalizaciones')]
    public function test_normaliza_la_placa(?string $entrada, ?string $esperada): void
    {
        $this->assertSame($esperada, Placa::normalizar($entrada));
    }

    public static function normalizaciones(): array
    {
        return [
            'mayusculas y guiones' => ['1234-abc', '1234ABC'],
            'espacios' => [' 123 ABC ', '123ABC'],
            'ya normalizada' => ['1234ABC', '1234ABC'],
            'vacia' => ['   ', null],
            'nula' => [null, null],
        ];
    }

    #[DataProvider('formatos')]
    public function test_valida_formato_boliviano(string $placa, bool $esperado): void
    {
        $this->assertSame($esperado, Placa::esValida($placa));
    }

    public static function formatos(): array
    {
        return [
            ['1234ABC', true],
            ['123ABC', true],
            ['12ABC', false],
            ['1234AB', false],
            ['ABCD123', false],
        ];
    }

    public function test_genera_variantes_de_confusiones_ocr(): void
    {
        // 1OBA5C: 1->I/L, O->0/D/Q, B->8, A->4 y 5->S (cada variante cambia un solo carácter)
        $variantes = Placa::variantesOcr('1OBA5C');

        $this->assertEqualsCanonicalizing(
            ['IOBA5C', 'LOBA5C', '10BA5C', '1DBA5C', '1QBA5C', '1O8A5C', '1OB45C', '1OBASC'],
            $variantes,
        );

        // Un 1 en lugar de una L al final (3983BZ1 -> 3983BZL).
        $this->assertContains('3983BZL', Placa::variantesOcr('3983BZ1'));
    }

    #[DataProvider('correcciones')]
    public function test_corrige_el_formato_por_posicion(string $leida, ?string $esperada): void
    {
        $this->assertSame($esperada, Placa::corregirFormato($leida));
    }

    public static function correcciones(): array
    {
        return [
            'ya valida' => ['1234ABC', '1234ABC'],
            'A leida como 4 (casos reales del video)' => ['2424YJ', '242AYJ'],
            'O leida como 0 en las letras' => ['1234AB0', '1234ABO'],
            'I leida como 1 en las letras' => ['1212YC1', '1212YCI'],
            'S leida como 5 en los digitos' => ['S95SHC', '595SHC'],
            'basura de un frame borroso' => ['40L1M0L', null],
            'largo imposible' => ['12AB', null],
        ];
    }

    #[DataProvider('mismosVehiculos')]
    public function test_reconoce_lecturas_del_mismo_vehiculo(string $a, string $b, bool $esperado): void
    {
        $this->assertSame($esperado, Placa::mismoVehiculo($a, $b));
    }

    public static function mismosVehiculos(): array
    {
        return [
            'iguales' => ['4027UGL', '4027UGL', true],
            'dos letras mal leidas (caso real)' => ['4027UGL', '4027MOL', true],
            'J leida como 3 (caso real)' => ['242AYJ', '242AY3', true],
            'un caracter distinto' => ['1234ABC', '1234ABD', true],
            'otro vehiculo' => ['4027UGL', '195SHC', false],
            'otros numeros, mismas letras' => ['1234ABC', '5678ABC', false],
            'distinto largo' => ['242AYJ', '2424AYJ', false],
        ];
    }
}
