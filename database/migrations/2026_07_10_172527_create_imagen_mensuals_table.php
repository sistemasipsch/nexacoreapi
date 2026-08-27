<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('imagen_mensual')) {
            Schema::create('imagen_mensual', function (Blueprint $table) {
                $table->id();
                $table->string('nombre_original');
                $table->string('nombre_archivo');
                $table->string('ruta');
                $table->integer('subido_por');
                $table->timestamps();

                $table->foreign('subido_por')->references('id')->on('usuarios');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imagen_mensual');
    }
};
