<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Vehiculo extends Model
{
    public const CATEGORIAS = ['moto', 'auto', 'minibus', 'camion', 'bus'];

    protected $table = 'vehiculos';

    protected $fillable = ['placa', 'propietario', 'categoria', 'ejes', 'saldo', 'activo', 'foto'];

    protected function casts(): array
    {
        return [
            'ejes' => 'integer',
            'saldo' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function pasos(): HasMany
    {
        return $this->hasMany(Paso::class);
    }

    /** URL pública de la imagen inferida del vehículo (o null si no tiene). */
    public function fotoUrl(): ?string
    {
        return $this->foto ? Storage::disk('public')->url($this->foto) : null;
    }

    /**
     * Guarda una imagen JPEG (bytes) como foto inferida del vehículo y borra la anterior.
     */
    public function guardarFoto(string $jpeg): void
    {
        $disco = Storage::disk('public');
        $anterior = $this->foto;
        $ruta = 'vehiculos/'.$this->placa.'-'.substr(md5($jpeg), 0, 8).'.jpg';

        $disco->put($ruta, $jpeg);
        $this->forceFill(['foto' => $ruta])->save();

        if ($anterior && $anterior !== $ruta) {
            $disco->delete($anterior);
        }
    }
}
