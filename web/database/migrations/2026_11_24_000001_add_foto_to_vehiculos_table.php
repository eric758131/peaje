<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imagen ya inferida del vehículo (placa, máscara y ruedas marcadas; personas cubiertas).
 * Ruta relativa al disco "public", por ejemplo vehiculos/3983BZL.jpg.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};
