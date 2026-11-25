<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Autenticable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Cuentas de acceso a la web (email y contraseña). Solo `activo = true` puede entrar.
 */
class Usuario extends Autenticable
{
    use HasApiTokens, Notifiable;

    public const ADMINISTRADOR = 'administrador';

    public const OPERADOR = 'operador';

    protected $table = 'usuarios';

    protected $fillable = ['email', 'password', 'nombre', 'rol', 'activo'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function esAdministrador(): bool
    {
        return $this->rol === self::ADMINISTRADOR;
    }

    public function pasos(): HasMany
    {
        return $this->hasMany(Paso::class, 'usuario_id');
    }
}
