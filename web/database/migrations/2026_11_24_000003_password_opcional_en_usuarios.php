<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Con el inicio de sesión de Google, una cuenta puede no tener contraseña (solo entra con Google).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
