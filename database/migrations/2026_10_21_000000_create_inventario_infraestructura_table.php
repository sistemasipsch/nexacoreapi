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
        if (!Schema::hasTable('inventario_infraestructura')) {
            Schema::create('inventario_infraestructura', function (Blueprint $table) {
                $table->increments('id');
                $table->string('nombre_equipo', 255)->comment('Nombre funcional del equipo');
                $table->string('tipo_equipo', 100)->comment('Ej: Minisplit, Cassette, Central, Piso Techo, Chiller, Ventana');
                $table->string('marca', 100)->nullable();
                $table->string('modelo', 100)->nullable();
                $table->string('serial', 150)->nullable()->unique('uk_inv_infra_serial');
                $table->string('activo_fijo', 100)->nullable()->unique('uk_inv_infra_activo_fijo');
                $table->string('imagen_url', 500)->nullable();
                $table->string('capacidad', 50)->nullable();
                $table->string('voltaje', 50)->nullable();
                $table->string('amperaje', 50)->nullable();
                $table->string('frecuencia', 50)->nullable()->default('60 Hz');
                
                $table->integer('sede_id');
                $table->integer('area_id')->nullable();
                $table->string('ubicacion_especifica', 255)->nullable();
                $table->integer('responsable_id')->nullable();
                $table->enum('forma_adquisicion', ['compra directa', 'alquiler', 'donacion', 'comodato'])->default('compra directa');
                $table->integer('proveedor_id')->nullable();
                $table->date('fecha_compra')->nullable();
                $table->integer('garantia_meses')->nullable();
                $table->date('fecha_instalacion')->nullable();
                $table->integer('vida_util_anos')->nullable();
                $table->string('frecuencia_mantenimiento', 50)->nullable()->default('Trimestral');
                $table->enum('estado_equipo', ['nuevo', 'usado', 'bueno', 'regular', 'malo', 'de_baja'])->default('nuevo');
                
                $table->text('descripcion_general')->nullable();
                $table->date('fecha_traslado')->nullable();
                $table->integer('traslado_dependencia_id')->nullable();
                $table->integer('traslado_area_id')->nullable();
                $table->text('observaciones')->nullable();
                $table->text('repuestos_principales')->nullable();
                $table->text('recomendaciones')->nullable();
                $table->text('equipos_adicionales')->nullable();
                $table->integer('creado_por')->nullable();
                $table->timestamps();

                // Índices
                $table->index('sede_id', 'idx_inv_infra_sede');
                $table->index('area_id', 'idx_inv_infra_area');
                $table->index('proveedor_id', 'idx_inv_infra_proveedor');
                $table->index('responsable_id', 'idx_inv_infra_responsable');
                $table->index('creado_por', 'idx_inv_infra_creado_por');
                $table->index('traslado_dependencia_id', 'idx_inv_infra_traslado_dep');
                $table->index('traslado_area_id', 'idx_inv_infra_traslado_area');

                // Claves Foráneas
                $table->foreign('area_id', 'fk_inv_infra_area')->references('id')->on('areas')->onDelete('set null')->onUpdate('cascade');
                $table->foreign('creado_por', 'fk_inv_infra_creado_por')->references('id')->on('usuarios')->onDelete('set null')->onUpdate('cascade');
                $table->foreign('proveedor_id', 'fk_inv_infra_proveedor')->references('id')->on('cp_proveedores')->onDelete('set null')->onUpdate('cascade');
                $table->foreign('responsable_id', 'fk_inv_infra_responsable')->references('id')->on('personal')->onDelete('set null')->onUpdate('cascade');
                $table->foreign('sede_id', 'fk_inv_infra_sede')->references('id')->on('sedes')->onDelete('restrict')->onUpdate('cascade');
                $table->foreign('traslado_area_id', 'fk_inv_infra_traslado_area')->references('id')->on('areas')->onDelete('set null')->onUpdate('cascade');
                $table->foreign('traslado_dependencia_id', 'fk_inv_infra_traslado_dep')->references('id')->on('dependencias_sedes')->onDelete('set null')->onUpdate('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventario_infraestructura');
    }
};
