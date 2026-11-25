<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('placa', 10)->unique();          // normalizada: mayúsculas, sin guiones ni espacios
            $table->string('propietario')->nullable();
            $table->string('categoria', 20);                 // moto | auto | minibus | camion | bus
            $table->unsignedTinyInteger('ejes')->default(2);
            $table->decimal('saldo', 10, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
