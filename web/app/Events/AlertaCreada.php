<?php

namespace App\Events;

use App\Models\Alerta;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se emite cuando se genera una alerta (placa no registrada o sin saldo).
 */
class AlertaCreada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array{pasos: int, recaudado: float, alertas_pendientes: int, fecha: string}  $resumen
     */
    public function __construct(public Alerta $alerta, public array $resumen) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('peaje')];
    }

    public function broadcastAs(): string
    {
        return 'alerta.creada';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'alerta' => [
                'id' => $this->alerta->id,
                'tipo' => $this->alerta->tipo,
                'placa' => $this->alerta->paso?->placa_normalizada,
                'caseta' => $this->alerta->paso?->caseta?->nombre,
                'imagen_url' => $this->alerta->paso?->imagen_url,
                'hora' => $this->alerta->created_at->format('H:i:s'),
            ],
            'resumen' => $this->resumen,
        ];
    }
}
