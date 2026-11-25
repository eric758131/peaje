<?php

namespace App\Services\Agente;

use Illuminate\Support\Facades\Cache;

/**
 * Memoria corta de una conversación con el agente: lo detectado en la última imagen y los últimos
 * mensajes. Vive en la caché unas horas. La web usa una por sesión; la app, una por `conversacion`.
 */
class MemoriaChat
{
    private const DURACION_SEGUNDOS = 3 * 3600;

    public function __construct(private string $clave) {}

    public static function web(string $idSesion): self
    {
        return new self('agente:web:'.$idSesion);
    }

    public static function api(string $conversacion): self
    {
        return new self('agente:api:'.$conversacion);
    }

    /** @return array<string, mixed>|null */
    public function escena(): ?array
    {
        return $this->datos()['escena'] ?? null;
    }

    /** @return list<array{role: string, content: string}> */
    public function historial(): array
    {
        return $this->datos()['historial'] ?? [];
    }

    /** @param  array<string, mixed>|null  $escena */
    public function guardarEscena(?array $escena): void
    {
        $this->guardar(['escena' => $escena] + $this->datos());
    }

    public function recordar(string $usuario, string $agente): void
    {
        $historial = [...$this->historial(),
            ['role' => 'user', 'content' => $usuario],
            ['role' => 'assistant', 'content' => $agente],
        ];

        $this->guardar(['historial' => array_slice($historial, -AgenteChat::HISTORIAL)] + $this->datos());
    }

    public function olvidar(): void
    {
        Cache::forget($this->clave);
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        return Cache::get($this->clave, []);
    }

    /** @param  array<string, mixed>  $datos */
    private function guardar(array $datos): void
    {
        Cache::put($this->clave, $datos, self::DURACION_SEGUNDOS);
    }
}
