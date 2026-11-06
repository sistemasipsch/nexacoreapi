<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('cp_pedidos')
            ->where('estado_compras', 'rechazado')
            ->orWhere('estado_gerencia', 'rechazado')
            ->pluck('id');

        DB::table('cp_items_pedidos')->whereIn('cp_pedido', $ids)->update([
            'estado' => 'rechazado',
            'comprado' => 0,
            'fecha_entregado' => null,
        ]);
    }

    public function down(): void
    {
        // No destructivo
    }
};
