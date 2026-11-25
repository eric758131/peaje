<?php

namespace App\Events;

use App\Models\Paso;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se emite cada vez que una caseta registra un vehículo.
 * El panel web lo recibe sin recargar la página.
 */
class PasoRegistrado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array{pasos: int, recaudado: float, alertas_pendientes: int, fecha: string}  $resumen
     */
    public function __construct(public Paso $paso, public array $resumen) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('peaje')];
    }

    public function broadcastAs(): string
    {
        return 'paso.registrado';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'paso' => [
                'id' => $this->paso->id,
                'placa' => $this->paso->placa_normalizada,
                'categoria' => $this->paso->categoria_detectada,
                'ejes' => $this->paso->ejes_detectados,
                'pasajeros' => $this->paso->pasajeros,
                'monto' => (float) $this->paso->monto,
                'estado' => $this->paso->estado,
                'caseta' => $this->paso->caseta?->nombre,
                'imagen_url' => $this->paso->imagen_url,
                'hora' => $this->paso->created_at->format('H:i:s'),
                'url' => route('pasos.show', $this->paso),
            ],
            'resumen' => $this->resumen,
        ];
    }
}
