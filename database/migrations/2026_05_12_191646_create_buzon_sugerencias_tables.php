<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('estados_ticket', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
        });

        DB::table('estados_ticket')->insert([
            ['nombre' => 'Abierto'],
            ['nombre' => 'En Proceso'],
            ['nombre' => 'Resuelto'],
            ['nombre' => 'Cerrado'],
        ]);

        Schema::create('buzon_sugerencia', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_ticket')->unique();
            $table->string('asunto');
            $table->text('observaciones');
            $table->unsignedBigInteger('estado_id');
            $table->string('prioridad')->default('Baja');
            $table->unsignedBigInteger('creado_por');
            $table->unsignedBigInteger('asignado_a')->nullable();
            
            $table->foreign('estado_id')->references('id')->on('estados_ticket');
            $table->foreign('creado_por')->references('id')->on('usuarios');
            $table->foreign('asignado_a')->references('id')->on('usuarios');
            
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_cierre')->nullable();
        });

        Schema::create('sugerencia_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sugerencia_id');
            $table->string('url_imagen');
            $table->timestamp('fecha_subida')->useCurrent();

            $table->foreign('sugerencia_id')->references('id')->on('buzon_sugerencia')->onDelete('cascade');
        });

        Schema::create('sugerencia_comentarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sugerencia_id');
            $table->unsignedBigInteger('usuario_id');
            $table->text('mensaje');
            $table->timestamp('fecha_comentario')->useCurrent();

            $table->foreign('sugerencia_id')->references('id')->on('buzon_sugerencia')->onDelete('cascade');
            $table->foreign('usuario_id')->references('id')->on('usuarios');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sugerencia_comentarios');
        Schema::dropIfExists('sugerencia_adjuntos');
        Schema::dropIfExists('buzon_sugerencia');
        Schema::dropIfExists('estados_ticket');
    }
};
