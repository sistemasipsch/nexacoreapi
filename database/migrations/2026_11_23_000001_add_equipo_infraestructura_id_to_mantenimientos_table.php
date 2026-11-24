<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega a `mantenimientos` las columnas que usa el modelo Mantenimiento
     * y el MantenimientoController pero que no existían en la BD:
     * serial, area_id y equipo_infraestructura_id.
     */
    public function up(): void
    {
        if (!Schema::hasTable('mantenimientos')) {
            return;
        }

        Schema::table('mantenimientos', function (Blueprint $table) {
            if (!Schema::hasColumn('mantenimientos', 'serial')) {
                $table->string('serial', 100)->nullable()->after('codigo');
            }

            if (!Schema::hasColumn('mantenimientos', 'area_id')) {
                $table->integer('area_id')->nullable()->after('sede_id');
                $table->foreign('area_id', 'fk_mantenimientos_area')
                    ->references('id')->on('areas')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('mantenimientos', 'equipo_infraestructura_id')) {
                $table->unsignedInteger('equipo_infraestructura_id')->nullable()->after('area_id');
                $table->foreign('equipo_infraestructura_id', 'fk_mantenimientos_equipo_infra')
                    ->references('id')->on('inventario_infraestructura')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mantenimientos', function (Blueprint $table) {
            if (Schema::hasColumn('mantenimientos', 'equipo_infraestructura_id')) {
                $table->dropForeign('fk_mantenimientos_equipo_infra');
                $table->dropColumn('equipo_infraestructura_id');
            }

            if (Schema::hasColumn('mantenimientos', 'area_id')) {
                $table->dropForeign('fk_mantenimientos_area');
                $table->dropColumn('area_id');
            }

            if (Schema::hasColumn('mantenimientos', 'serial')) {
                $table->dropColumn('serial');
            }
        });
    }
};
