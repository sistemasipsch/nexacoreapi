<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Actualiza firma_sistemas de todos los mantenimientos de PC
     * creados por Sergio Andres Prosperini Macias (ID 7)
     * con la firma_digital que tiene registrada en la tabla usuarios.
     */
    public function up(): void
    {
        $firmaPath = DB::table('usuarios')
            ->where('id', 7)
            ->value('firma_digital');

        if ($firmaPath) {
            DB::table('pc_mantenimientos')
                ->where('creado_por', 7)
                ->update(['firma_sistemas' => $firmaPath]);
        }
    }

    /**
     * No se hace rollback de datos históricos de firma.
     */
    public function down(): void
    {
        // Irreversible: no se restauran las firmas individuales anteriores.
    }
};
