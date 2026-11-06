<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cp_items_pedidos') && !Schema::hasColumn('cp_items_pedidos', 'estado')) {
            Schema::table('cp_items_pedidos', function (Blueprint $table) {
                // pendiente | procesado | entregado
                $table->string('estado', 20)->default('pendiente');
            });
            DB::table('cp_items_pedidos')->where('comprado', 1)->update(['estado' => 'entregado']);
        }

        if (Schema::hasTable('cp_pedidos') && !Schema::hasColumn('cp_pedidos', 'color_motivo_compras')) {
            Schema::table('cp_pedidos', function (Blueprint $table) {
                // verde | naranja | rojo
                $table->string('color_motivo_compras', 10)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cp_items_pedidos', 'estado')) {
            Schema::table('cp_items_pedidos', fn (Blueprint $t) => $t->dropColumn('estado'));
        }
        if (Schema::hasColumn('cp_pedidos', 'color_motivo_compras')) {
            Schema::table('cp_pedidos', fn (Blueprint $t) => $t->dropColumn('color_motivo_compras'));
        }
    }
};