<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pasos', function (Blueprint $table) {
            $table->id();
            $table->string('placa_leida', 20)->nullable();
            $table->string('placa_normalizada', 10)->nullable()->index();
            $table->decimal('confianza_ocr', 5, 2)->nullable();
            $table->string('categoria_detectada', 20)->nullable();
            $table->unsignedTinyInteger('ejes_detectados')->nullable();
            $table->unsignedTinyInteger('pasajeros')->nullable();      // bonus: conteo por segmentación
            $table->decimal('monto', 8, 2)->default(0);
            $table->foreignId('caseta_id')->constrained('casetas')->restrictOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->string('imagen_url')->nullable();
            $table->string('estado', 20);                               // cobrado | sin_saldo | no_registrado
            $table->unsignedInteger('latencia_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pasos');
    }
};
