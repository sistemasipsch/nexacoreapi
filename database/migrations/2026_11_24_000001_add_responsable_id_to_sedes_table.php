<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Responsable (usuario) de cada sede. Es quien firma como "personal a cargo"
 * en el formato de mantenimientos de infraestructura.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sedes', 'responsable_id')) {
            Schema::table('sedes', function (Blueprint $table) {
                $table->unsignedBigInteger('responsable_id')->nullable()->after('nombre');
                $table->index('responsable_id', 'fk_sedes_responsable');
            });
        }

        // Responsables actuales (coordinadores de los mantenimientos de cada sede).
        // Se buscan por nombre para no depender de ids; si no existen, la sede queda sin responsable.
        $responsables = [
            'SEDE PRINCIPAL' => 'DIANA CAROLINA AVENDAÑO VANEGAS',
            'SEDE CAOBOS II' => 'NATALIA ELVIRA CARRASCO VARGAS',
            'SEDE PAMI' => 'MARYOLI ACEVEDO MORA',
        ];

        foreach ($responsables as $sede => $usuario) {
            $usuarioId = DB::table('usuarios')
                ->whereRaw('UPPER(TRIM(nombre_completo)) = ?', [mb_strtoupper($usuario)])
                ->orderByDesc('estado')
                ->value('id');

            if ($usuarioId) {
                DB::table('sedes')
                    ->whereRaw('UPPER(TRIM(nombre)) = ?', [$sede])
                    ->whereNull('responsable_id')
                    ->update(['responsable_id' => $usuarioId]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sedes', 'responsable_id')) {
            Schema::table('sedes', function (Blueprint $table) {
                $table->dropIndex('fk_sedes_responsable');
                $table->dropColumn('responsable_id');
            });
        }
    }
};
