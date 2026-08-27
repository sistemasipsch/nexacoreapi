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
        if (!Schema::hasTable('cp_pedidos_programados')) {
            Schema::create('cp_pedidos_programados', function (Blueprint $table) {
                $table->id();
                $table->json('datos_pedido')->comment('Payload completo para la creación del pedido');
                $table->dateTime('fecha_programada')->comment('Fecha en la que debe crearse el pedido real');
                $table->string('firma_programador')->nullable()->comment('Ruta física de la firma de quien programa');
                $table->integer('creado_por')->comment('Usuario que programó el pedido');
                $table->enum('estado', ['programado', 'ejecutado', 'cancelado', 'error'])->default('programado')->comment('Estado de la programación');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cp_pedidos_programados');
    }
};
