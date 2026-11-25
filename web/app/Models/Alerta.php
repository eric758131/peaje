<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alerta extends Model
{
    public const NO_REGISTRADA = 'no_registrada';

    public const SIN_SALDO = 'sin_saldo';

    protected $table = 'alertas';

    protected $fillable = ['paso_id', 'tipo', 'atendida'];

    protected function casts(): array
    {
        return ['atendida' => 'boolean'];
    }

    public function paso(): BelongsTo
    {
        return $this->belongsTo(Paso::class);
    }
}
