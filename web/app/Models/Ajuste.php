<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Ajustes clave/valor guardados en la base de datos. Si un ajuste no existe se usa el valor del .env.
 * Los secretos (token) se guardan cifrados con APP_KEY.
 */
class Ajuste extends Model
{
    public const INFERENCIA_DRIVER = 'inferencia.driver';

    public const INFERENCIA_URL = 'inferencia.url';

    public const INFERENCIA_TOKEN = 'inferencia.token';

    private const CACHE = 'ajustes';

    private const CIFRADOS = [self::INFERENCIA_TOKEN];

    protected $table = 'ajustes';

    protected $primaryKey = 'clave';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['clave', 'valor'];

    /** Valor guardado del ajuste, o $defecto si no está guardado (o la tabla aún no existe). */
    public static function valor(string $clave, mixed $defecto = null): mixed
    {
        $valor = static::todos()[$clave] ?? null;

        if ($valor === null) {
            return $defecto;
        }

        if (in_array($clave, self::CIFRADOS, true)) {
            try {
                return Crypt::decryptString($valor);
            } catch (DecryptException) {
                return $defecto;  // cambió APP_KEY: el token guardado ya no sirve
            }
        }

        return $valor;
    }

    public static function guardar(string $clave, ?string $valor): void
    {
        if ($valor === null) {
            static::query()->whereKey($clave)->delete();
        } else {
            $guardado = in_array($clave, self::CIFRADOS, true) ? Crypt::encryptString($valor) : $valor;
            static::query()->updateOrCreate(['clave' => $clave], ['valor' => $guardado]);
        }

        Cache::forget(self::CACHE);
    }

    public static function tiene(string $clave): bool
    {
        return array_key_exists($clave, static::todos());
    }

    /** @return array<string, string|null> */
    private static function todos(): array
    {
        try {
            return Cache::rememberForever(self::CACHE, fn () => static::query()->pluck('valor', 'clave')->all());
        } catch (\Throwable) {
            return [];  // sin base de datos o sin migrar: se usa el .env
        }
    }
}
