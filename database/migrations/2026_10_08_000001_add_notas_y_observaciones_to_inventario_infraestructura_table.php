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
        if (Schema::hasTable('inventario_infraestructura')) {
            Schema::table('inventario_infraestructura', function (Blueprint $table) {
                if (!Schema::hasColumn('inventario_infraestructura', 'descripcion_general')) {
                    $table->text('descripcion_general')->nullable()->after('estado_equipo')
                        ->comment('Descripción detallada del uso u objetivo del equipo');
                }
                if (!Schema::hasColumn('inventario_infraestructura', 'repuestos_principales')) {
                    $table->text('repuestos_principales')->nullable()->after('observaciones')
                        ->comment('Piezas cambiadas recientemente o requeridas');
                }
                if (!Schema::hasColumn('inventario_infraestructura', 'recomendaciones')) {
                    $table->text('recomendaciones')->nullable()->after('repuestos_principales')
                        ->comment('Precauciones y mantenimientos preventivos recomendados');
                }
                if (!Schema::hasColumn('inventario_infraestructura', 'equipos_adicionales')) {
                    $table->text('equipos_adicionales')->nullable()->after('recomendaciones')
                        ->comment('Equipos o accesorios adicionales asignados');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('inventario_infraestructura')) {
            Schema::table('inventario_infraestructura', function (Blueprint $table) {
                $columns = ['descripcion_general', 'repuestos_principales', 'recomendaciones', 'equipos_adicionales'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('inventario_infraestructura', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
