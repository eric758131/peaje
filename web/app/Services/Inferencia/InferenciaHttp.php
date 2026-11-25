<?php

namespace App\Services\Inferencia;

use App\Services\Inferencia\Local\Imagen;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Inferencia en un endpoint externo (Colab o RunPod).
 * El link y el token se configuran en la web (Estado de la IA) o en INFERENCE_URL / INFERENCE_TOKEN.
 * Contrato: docs/contrato-inferencia.md
 */
class InferenciaHttp implements Inferencia
{
    private string $base;

    private ?string $token;

    public function __construct(?string $url = null, ?string $token = null)
    {
        $this->base = rtrim((string) ($url ?? config('peaje.inferencia.url')), '/');
        $this->token = $token ?? config('peaje.inferencia.token');
    }

    public function inferir(string $rutaImagen): array
    {
        try {
            $respuesta = $this->cliente()
                ->attach('imagen', $this->imagenParaEnviar($rutaImagen), 'frame.jpg')
                ->post($this->url('/inferir'));
        } catch (ConnectionException $e) {
            throw new InferenciaException('No hay conexión con el endpoint de inferencia.', 0, $e);
        }

        if (! $respuesta->successful()) {
            throw new InferenciaException('El endpoint de inferencia respondió con error '.$respuesta->status().'.');
        }

        return $respuesta->json() ?? throw new InferenciaException('La respuesta de inferencia no es JSON.');
    }

    public function estaDisponible(): array
    {
        if ($this->base === '') {
            return ['disponible' => false, 'driver' => 'http', 'detalle' => 'Falta el link del endpoint de inferencia.'];
        }

        try {
            $respuesta = $this->cliente()->get($this->url('/health'));
            // /health es público: el token solo se comprueba en /inferir.
            $codigo = $respuesta->successful() ? $this->codigoConToken() : $respuesta->status();
            $rechazado = in_array($codigo, [401, 403], true);

            return [
                'disponible' => $respuesta->successful() && ! $rechazado,
                'driver' => 'http',
                'detalle' => match (true) {
                    $rechazado => 'El endpoint rechazó el token (código '.$codigo.'). Copia el token nuevo de la celda 10.',
                    $respuesta->successful() => 'Responde correctamente.',
                    default => 'Código '.$respuesta->status().'.',
                },
                'url' => $this->base,
                // Modelo del agente conversacional cargado en Colab (null = no cargado).
                'agente' => $respuesta->successful() ? ($respuesta->json('agente') ?: false) : null,
            ];
        } catch (ConnectionException $e) {
            return ['disponible' => false, 'driver' => 'http', 'detalle' => 'No hay conexión con el endpoint. Si reiniciaste Colab, el link cambió.', 'url' => $this->base];
        }
    }

    /**
     * Envía a /inferir una imagen mínima (64x64 gris) para saber si el endpoint acepta el token.
     * Devuelve el código HTTP (401/403 = token rechazado), o null si no se pudo comprobar.
     */
    private function codigoConToken(): ?int
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $imagen = imagecreatetruecolor(64, 64);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, 128, 128, 128));
        ob_start();
        imagejpeg($imagen, null, 70);
        $jpeg = (string) ob_get_clean();

        try {
            return $this->cliente()->attach('imagen', $jpeg, 'prueba.jpg')->post($this->url('/inferir'))->status();
        } catch (ConnectionException) {
            return null;
        }
    }

    /**
     * El túnel hasta Colab es lento para transferir (~100 KB/s): la imagen se envía como máximo a
     * INFERENCE_LADO_ENVIO px (1280) y recomprimida a JPEG 85 (con 80 el OCR ya falla en algunas). No se baja la
     * resolución: con 1024 px o menos el OCR se equivoca más en placas chicas (242AYJ -> 242AYS).
     */
    private function imagenParaEnviar(string $rutaImagen): string
    {
        $lado = (int) config('peaje.inferencia.lado_envio', 1280);
        $original = (string) file_get_contents($rutaImagen);

        if ($lado <= 0 || ! extension_loaded('gd')) {
            return $original;
        }

        try {
            $imagen = Imagen::cargar($rutaImagen, $lado);  // también corrige la orientación EXIF
        } catch (\Throwable) {
            return $original;
        }

        ob_start();
        imagejpeg($imagen, null, 85);
        $reducida = (string) ob_get_clean();

        return strlen($reducida) < strlen($original) ? $reducida : $original;
    }

    private function cliente()
    {
        // El túnel gratuito de Cloudflare a veces corta una conexión: se reintenta hasta 2 veces
        // (solo si falló la conexión; un error del endpoint no se reintenta).
        $cliente = Http::timeout((int) config('peaje.inferencia.timeout'))
            ->connectTimeout(10)
            ->retry(3, 500, fn ($e) => $e instanceof ConnectionException, throw: false)
            ->acceptJson();

        return $this->token ? $cliente->withHeaders(['X-API-Key' => $this->token]) : $cliente;
    }

    private function url(string $ruta): string
    {
        if ($this->base === '') {
            throw new InferenciaException('Falta el link del endpoint de inferencia (configúralo en Estado de la IA).');
        }

        return $this->base.$ruta;
    }
}
