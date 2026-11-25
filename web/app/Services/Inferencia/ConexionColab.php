<?php

namespace App\Services\Inferencia;

use App\Models\Ajuste;

/**
 * Link y token del endpoint de Colab: lo guardado en la web (Estado de la IA → Conexión con Colab)
 * o, si no hay nada guardado, INFERENCE_DRIVER / INFERENCE_URL / INFERENCE_TOKEN del .env.
 * Lo usan la inferencia de imágenes y el agente conversacional (mismo endpoint).
 */
class ConexionColab
{
    public static function driver(): string
    {
        return (string) Ajuste::valor(Ajuste::INFERENCIA_DRIVER, config('peaje.inferencia.driver'));
    }

    public static function url(): string
    {
        return rtrim((string) Ajuste::valor(Ajuste::INFERENCIA_URL, config('peaje.inferencia.url')), '/');
    }

    public static function token(): ?string
    {
        return Ajuste::valor(Ajuste::INFERENCIA_TOKEN, config('peaje.inferencia.token')) ?: null;
    }
}
