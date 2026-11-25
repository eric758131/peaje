<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifas', function (Blueprint $table) {
            $table->id();
            $table->string('categoria', 20);
            $table->unsignedTinyInteger('ejes');
            $table->decimal('monto', 8, 2);
            $table->timestamps();

            $table->unique(['categoria', 'ejes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifas');
    }
};
