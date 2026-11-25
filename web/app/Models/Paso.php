<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un paso es el registro de un vehículo que pasó por una caseta.
 */
class Paso extends Model
{
    public const COBRADO = 'cobrado';

    public const SIN_SALDO = 'sin_saldo';

    public const NO_REGISTRADO = 'no_registrado';

    protected $table = 'pasos';

    protected $fillable = [
        'placa_leida',
        'placa_normalizada',
        'confianza_ocr',
        'categoria_detectada',
        'ejes_detectados',
        'pasajeros',
        'monto',
        'caseta_id',
        'usuario_id',
        'vehiculo_id',
        'imagen_url',
        'estado',
        'latencia_ms',
    ];

    protected function casts(): array
    {
        return [
            'confianza_ocr' => 'decimal:2',
            'monto' => 'decimal:2',
            'ejes_detectados' => 'integer',
            'pasajeros' => 'integer',
            'latencia_ms' => 'integer',
        ];
    }

    public function caseta(): BelongsTo
    {
        return $this->belongsTo(Caseta::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class);
    }

    /**
     * Forma del paso en las respuestas JSON.
     *
     * @return array<string, mixed>
     */
    public function aArregloApi(): array
    {
        return [
            'id' => $this->id,
            'placa' => $this->placa_normalizada,
            'categoria' => $this->categoria_detectada,
            'ejes' => $this->ejes_detectados,
            'pasajeros' => $this->pasajeros,
            'monto' => (float) $this->monto,
            'estado' => $this->estado,
            'imagen_url' => $this->imagen_url,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
