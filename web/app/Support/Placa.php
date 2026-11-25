<?php

namespace App\Support;

/**
 * Normalización y validación de placas bolivianas.
 *
 * Una placa normalizada son solo mayúsculas y dígitos/letras: "1234-abc" => "1234ABC".
 */
final class Placa
{
    public static function normalizar(?string $texto): ?string
    {
        if ($texto === null) {
            return null;
        }

        $limpia = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($texto)));

        return $limpia === '' ? null : $limpia;
    }

    public static function esValida(?string $placa): bool
    {
        return $placa !== null && preg_match(config('peaje.formato_placa'), $placa) === 1;
    }

    /**
     * Placas que aparecen en un mensaje del chat ("¿la 1234-abc pasó?" => ["1234ABC"]).
     * Toma palabras de 5 a 8 caracteres con al menos 2 dígitos y 1 letra, así también entran
     * placas mal escritas (1234A8C) para que el agente sugiera la correcta.
     *
     * @return list<string>
     */
    public static function enTexto(string $texto, int $maximo = 3): array
    {
        // Une "1234 ABC" o "1234-ABC" en una sola palabra.
        $texto = preg_replace('/\b(\d{3,4})[\s.\-]+([A-Za-z]{2,3})\b/u', '$1$2', $texto);

        $placas = [];
        foreach (preg_split('/[^A-Za-z0-9]+/', $texto, -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
            $palabra = strtoupper($palabra);
            if (strlen($palabra) >= 5 && strlen($palabra) <= 8
                && preg_match_all('/\d/', $palabra) >= 2 && preg_match('/[A-Z]/', $palabra)) {
                $placas[$palabra] = true;
            }
        }

        return array_slice(array_keys($placas), 0, $maximo);
    }

    /** Letra que el OCR suele leer en lugar de un dígito, y al revés. */
    private const A_DIGITO = ['O' => '0', 'D' => '0', 'Q' => '0', 'I' => '1', 'L' => '1', 'Z' => '2', 'A' => '4', 'S' => '5', 'G' => '6', 'T' => '7', 'B' => '8'];

    private const A_LETRA = ['0' => 'O', '1' => 'I', '2' => 'Z', '4' => 'A', '5' => 'S', '6' => 'G', '7' => 'T', '8' => 'B'];

    /**
     * Si la lectura no tiene formato boliviano, intenta corregirla por posición:
     * los primeros 3 o 4 caracteres deben ser dígitos y los 3 últimos letras.
     * "2424YJ" => "242AYJ". Solo se cambia UN carácter: con más cambios ya no es
     * corregir sino adivinar ("40L1M0L" no se convierte en "4011MOL").
     * Devuelve null si no hay una corrección válida.
     */
    public static function corregirFormato(string $placa): ?string
    {
        if (self::esValida($placa)) {
            return $placa;
        }

        $largo = strlen($placa);
        if ($largo !== 6 && $largo !== 7) {
            return null;
        }

        $digitos = $largo - 3;
        $corregida = '';

        foreach (str_split($placa) as $i => $c) {
            $corregida .= $i < $digitos
                ? (ctype_digit($c) ? $c : (self::A_DIGITO[$c] ?? $c))
                : (ctype_alpha($c) ? $c : (self::A_LETRA[$c] ?? $c));
        }

        $cambios = count(array_diff_assoc(str_split($corregida), str_split($placa)));

        return $cambios === 1 && self::esValida($corregida) ? $corregida : null;
    }

    /**
     * ¿Dos lecturas son, con mucha probabilidad, el mismo vehículo? Se usa solo entre frames
     * de la misma caseta separados por pocos segundos (un video lee la placa varias veces y
     * a veces se equivoca en las letras: 4027UGL / 4027MOL, 242AYJ / 242AY3).
     *
     * Sí cuando: son iguales, difieren en un solo carácter (mismo largo), o comparten el
     * bloque numérico inicial de 3-4 dígitos y tienen el mismo largo.
     */
    public static function mismoVehiculo(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if (strlen($a) !== strlen($b)) {
            return false;
        }

        if (count(array_diff_assoc(str_split($a), str_split($b))) <= 1) {
            return true;
        }

        preg_match('/^\d{3,4}/', $a, $numerosA);
        preg_match('/^\d{3,4}/', $b, $numerosB);

        return ($numerosA[0] ?? null) !== null && ($numerosA[0] ?? null) === ($numerosB[0] ?? null);
    }

    /**
     * Variantes de una placa cambiando un solo carácter que el OCR suele confundir
     * (O↔0, I↔1↔L, B↔8, S↔5, Z↔2...). Sirven para sugerir "¿Quisiste decir…?".
     *
     * @return list<string>
     */
    public static function variantesOcr(string $placa): array
    {
        $confusiones = config('peaje.confusiones_ocr');
        $variantes = [];

        foreach (str_split($placa) as $posicion => $caracter) {
            foreach ((array) ($confusiones[$caracter] ?? []) as $reemplazo) {
                $variante = $placa;
                $variante[$posicion] = $reemplazo;
                $variantes[] = $variante;
            }
        }

        return array_values(array_unique($variantes));
    }
}
