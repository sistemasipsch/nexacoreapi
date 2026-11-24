<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sede de origen del último traslado. Antes se guardaba el id de la sede en
     * `traslado_dependencia_id`, que apunta a `dependencias_sedes`, y el frontend
     * mostraba una dependencia equivocada en lugar de la sede.
     */
    public function up(): void
    {
        if (!Schema::hasTable('inventario_infraestructura') || Schema::hasColumn('inventario_infraestructura', 'traslado_sede_id')) {
            return;
        }

        Schema::table('inventario_infraestructura', function (Blueprint $table) {
            $table->integer('traslado_sede_id')->nullable()->after('fecha_traslado');
            $table->foreign('traslado_sede_id', 'fk_inv_infra_traslado_sede')
                ->references('id')->on('sedes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventario_infraestructura', 'traslado_sede_id')) {
            Schema::table('inventario_infraestructura', function (Blueprint $table) {
                $table->dropForeign('fk_inv_infra_traslado_sede');
                $table->dropColumn('traslado_sede_id');
            });
        }
    }
};
