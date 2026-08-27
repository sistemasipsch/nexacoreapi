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
        if (Schema::hasTable('cp_items_pedidos') && !Schema::hasColumn('cp_items_pedidos', 'fecha_entregado')) {
            Schema::table('cp_items_pedidos', function (Blueprint $table) {
                $table->dateTime('fecha_entregado')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cp_items_pedidos') && Schema::hasColumn('cp_items_pedidos', 'fecha_entregado')) {
            Schema::table('cp_items_pedidos', function (Blueprint $table) {
                $table->dropColumn('fecha_entregado');
            });
        }
    }
};
