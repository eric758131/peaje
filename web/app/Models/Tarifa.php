<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tarifa extends Model
{
    protected $table = 'tarifas';

    protected $fillable = ['categoria', 'ejes', 'monto'];

    protected function casts(): array
    {
        return [
            'ejes' => 'integer',
            'monto' => 'decimal:2',
        ];
    }
}
