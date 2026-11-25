<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Cuentas iniciales.
 *
 * - El docente se registra como administrador (dato del lab).
 * - Los demás administradores van en ADMIN_EMAILS (.env), para no subir correos al repositorio.
 * - La contraseña inicial sale de ADMIN_PASSWORD (.env). Si está vacía se genera una al azar
 *   y se muestra en la consola. Solo se asigna al crear la cuenta: volver a sembrar no la cambia.
 */
class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $admins = array_unique(['doc.alvaro.pinto.ma@unifranz.edu.bo', ...config('peaje.admins_iniciales')]);

        foreach ($admins as $email) {
            $this->crear($email, $email === 'doc.alvaro.pinto.ma@unifranz.edu.bo' ? 'Docente' : $email, Usuario::ADMINISTRADOR);
        }

        // Cuentas de prueba solo en desarrollo: en producción no debe existir una cuenta de ejemplo activa.
        if (app()->environment('local', 'testing')) {
            $this->crear('admin@peaje.test', 'Administrador de prueba', Usuario::ADMINISTRADOR, 'password');
            $this->crear('operador@peaje.test', 'Operador de prueba', Usuario::OPERADOR, 'password');
        }
    }

    private function crear(string $email, string $nombre, string $rol, ?string $password = null): void
    {
        $email = mb_strtolower(trim($email));

        if (Usuario::where('email', $email)->exists()) {
            return;
        }

        $password ??= config('peaje.admin_password') ?: Str::password(14, symbols: false);

        Usuario::create(['email' => $email, 'password' => $password, 'nombre' => $nombre, 'rol' => $rol, 'activo' => true]);

        $this->command?->info("Cuenta {$email} ({$rol}) creada con contraseña: {$password}");
    }
}
