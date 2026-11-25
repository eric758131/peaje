<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes que el administrador cambia desde la web sin tocar el .env
 * (por ejemplo el link y el token del endpoint de Colab, que cambian en cada sesión).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes', function (Blueprint $table) {
            $table->string('clave')->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
    }
};
