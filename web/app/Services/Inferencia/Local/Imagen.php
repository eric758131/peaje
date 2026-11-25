<?php

namespace App\Services\Inferencia\Local;

use GdImage;
use RuntimeException;

/**
 * Preparación de imágenes con GD (incluido en casi todos los hostings PHP).
 */
class Imagen
{
    /** @var array<int, string>|null float32 de v/255 para cada byte 0..255 */
    private static ?array $tablaFloat = null;

    /**
     * Abre JPG, PNG o WEBP, corrige la orientación EXIF y reduce las fotos grandes.
     */
    public static function cargar(string $ruta, int $ladoMaximo = 1280): GdImage
    {
        $contenido = @file_get_contents($ruta);
        $imagen = $contenido === false ? false : @imagecreatefromstring($contenido);

        if (! $imagen instanceof GdImage) {
            throw new RuntimeException('No se pudo abrir la imagen.');
        }

        $imagen = self::orientar($imagen, $ruta);

        if (! imageistruecolor($imagen)) {
            imagepalettetotruecolor($imagen);
        }

        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $escala = $ladoMaximo / max($ancho, $alto);

        if ($escala < 1) {
            $reducida = imagecreatetruecolor(max(1, (int) round($ancho * $escala)), max(1, (int) round($alto * $escala)));
            imagecopyresampled($reducida, $imagen, 0, 0, 0, 0, imagesx($reducida), imagesy($reducida), $ancho, $alto);
            $imagen = $reducida;
        }

        return $imagen;
    }

    /**
     * Las fotos de celular guardan la rotación en EXIF en lugar de girar los píxeles.
     */
    private static function orientar(GdImage $imagen, string $ruta): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $imagen;
        }

        $exif = @exif_read_data($ruta);
        $orientacion = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;

        return match ((int) $orientacion) {
            3 => imagerotate($imagen, 180, 0),
            6 => imagerotate($imagen, -90, 0),
            8 => imagerotate($imagen, 90, 0),
            default => $imagen,
        };
    }

    /**
     * Letterbox como Ultralytics: escala sin deformar y rellena con gris (114) hasta un cuadrado.
     * El tensor es float32 RGB normalizado 0..1 en orden CHW.
     *
     * @return array{tensor: string, escala: float, px: float, py: float, lado: int}
     */
    public static function letterbox(GdImage $imagen, int $lado): array
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $escala = min($lado / $alto, $lado / $ancho);
        $nuevoAncho = (int) round($ancho * $escala);
        $nuevoAlto = (int) round($alto * $escala);
        $px = ($lado - $nuevoAncho) / 2;
        $py = ($lado - $nuevoAlto) / 2;

        $lienzo = imagecreatetruecolor($lado, $lado);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 114, 114, 114));
        imagecopyresampled($lienzo, $imagen, (int) round($px - 0.1), (int) round($py - 0.1), 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        return [
            'tensor' => self::chwFloat($lienzo),
            'escala' => $escala,
            'px' => $px,
            'py' => $py,
            'lado' => $lado,
        ];
    }

    /**
     * Recorta y redimensiona una región a ancho x alto. Devuelve bytes RGB (HWC, uint8).
     */
    public static function recorteRgb(GdImage $imagen, int $x1, int $y1, int $x2, int $y2, int $ancho, int $alto): string
    {
        $destino = imagecreatetruecolor($ancho, $alto);
        imagecopyresampled($destino, $imagen, 0, 0, $x1, $y1, $ancho, $alto, max(1, $x2 - $x1), max(1, $y2 - $y1));

        $bytes = '';
        for ($y = 0; $y < $alto; $y++) {
            for ($x = 0; $x < $ancho; $x++) {
                $c = imagecolorat($destino, $x, $y);
                $bytes .= chr(($c >> 16) & 0xFF).chr(($c >> 8) & 0xFF).chr($c & 0xFF);
            }
        }

        return $bytes;
    }

    /**
     * Píxeles de una imagen cuadrada a float32 en planos R, G y B.
     */
    private static function chwFloat(GdImage $imagen): string
    {
        $tabla = self::$tablaFloat ??= array_map(fn ($v) => pack('g', $v / 255), range(0, 255));

        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $r = $g = $b = '';

        for ($y = 0; $y < $alto; $y++) {
            for ($x = 0; $x < $ancho; $x++) {
                $c = imagecolorat($imagen, $x, $y);
                $r .= $tabla[($c >> 16) & 0xFF];
                $g .= $tabla[($c >> 8) & 0xFF];
                $b .= $tabla[$c & 0xFF];
            }
        }

        return $r.$g.$b;
    }

    public static function jpegBase64(GdImage $imagen, int $calidad = 82): string
    {
        ob_start();
        imagejpeg($imagen, null, $calidad);

        return base64_encode((string) ob_get_clean());
    }
}
