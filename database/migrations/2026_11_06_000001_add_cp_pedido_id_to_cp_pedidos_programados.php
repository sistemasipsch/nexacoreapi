<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cp_pedidos_programados') && !Schema::hasColumn('cp_pedidos_programados', 'cp_pedido_id')) {
            Schema::table('cp_pedidos_programados', function (Blueprint $table) {
                $table->unsignedBigInteger('cp_pedido_id')->nullable()->after('estado')
                    ->comment('Pedido real generado al ejecutar la programación');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cp_pedidos_programados', 'cp_pedido_id')) {
            Schema::table('cp_pedidos_programados', function (Blueprint $table) {
                $table->dropColumn('cp_pedido_id');
            });
        }
    }
};
