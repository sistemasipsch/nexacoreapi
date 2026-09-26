<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Asegurar dependencias de Sede Caobos II (sede_id = 2)
        $dependenciasCaobos = [
            'ATENCIÓN DOMICILIARIA',
            'ATENCIÓN DOMICILIARIA - ENFERMERÍA',
            'ATENCIÓN DOMICILIARIA - REHABILITACIÓN',
            'CUENTAS MÉDICAS',
            'SIAU',
            'ADMINISTRATIVO'
        ];

        $caobosDepIds = [];
        foreach ($dependenciasCaobos as $nombreDep) {
            $exist = DB::table('dependencias_sedes')
                ->where('sede_id', 2)
                ->where('nombre', $nombreDep)
                ->first();

            if (!$exist) {
                $id = DB::table('dependencias_sedes')->insertGetId([
                    'sede_id' => 2,
                    'nombre' => $nombreDep
                ]);
                $caobosDepIds[$nombreDep] = $id;
            } else {
                $caobosDepIds[$nombreDep] = $exist->id;
            }
        }

        // 2. Corregir entregas de activos fijos que corresponden a Sede Caobos II
        // Entrega 53: ANDREA DEL PILAR GONZALEZ CONTRERAS (SIAU / SEDE CAOBOS - ADMINISTRATIVOS)
        DB::table('cp_entrega_activos_fijos')->where('id', 53)->update([
            'sede_id' => 2,
            'proceso_solicitante' => $caobosDepIds['SIAU'] ?? 29
        ]);

        // Entrega 57: LEIDDY VIVIANA RAMIREZ ACEVEDO (ATENCION DOMICILIARIA)
        DB::table('cp_entrega_activos_fijos')->where('id', 57)->update([
            'sede_id' => 2,
            'proceso_solicitante' => $caobosDepIds['ATENCIÓN DOMICILIARIA'] ?? 3
        ]);

        // Entrega 58: MARIA NELLA SANJUAN HERNANDEZ (ATENCION DOMICILIARIA)
        DB::table('cp_entrega_activos_fijos')->where('id', 58)->update([
            'sede_id' => 2,
            'proceso_solicitante' => $caobosDepIds['ATENCIÓN DOMICILIARIA'] ?? 3
        ]);

        // Entrega 59: GARDENIA LISBETH ROJAS PRADA (ATENCION DOMICILIARIA)
        DB::table('cp_entrega_activos_fijos')->where('id', 59)->update([
            'sede_id' => 2,
            'proceso_solicitante' => $caobosDepIds['ATENCIÓN DOMICILIARIA'] ?? 3
        ]);

        // Entrega 60: MARIA FERNANDA GRISMALDO VILLAMIZAR (ATENCION DOMICILIARIA - ENFERMERIA)
        DB::table('cp_entrega_activos_fijos')->where('id', 60)->update([
            'sede_id' => 2,
            'proceso_solicitante' => $caobosDepIds['ATENCIÓN DOMICILIARIA - ENFERMERÍA'] ?? 4
        ]);

        // Entrega 61: ROSA LILIANA PERALTA CONTRERAS (ATENCION DOMICILIARIA - REHABILITACION)
        DB::table('cp_entrega_activos_fijos')->where('id', 61)->update([
            'sede_id' => 2,
            'proceso_solicitante' => $caobosDepIds['ATENCIÓN DOMICILIARIA - REHABILITACIÓN'] ?? 5
        ]);

        // Entrega 74: ALEXIS RANGEL MANOSALVA (CUENTAS MEDICAS - CAOBOS)
        DB::table('cp_entrega_activos_fijos')->where('id', 74)->update([
            'sede_id' => 2,
            'proceso_solicitante' => $caobosDepIds['CUENTAS MÉDICAS'] ?? 16
        ]);

        // 3. Corregir sede_id en inventario para items que pertenecen a Caobos o Archivo
        DB::table('inventario')
            ->where('dependencia', 'like', '%CAOBOS%')
            ->where('sede_id', 1)
            ->update(['sede_id' => 2]);

        DB::table('inventario')
            ->where('dependencia', 'like', '%ARCHIVO%')
            ->where('sede_id', 1)
            ->update(['sede_id' => 6]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir entregas a sede 1
        DB::table('cp_entrega_activos_fijos')
            ->whereIn('id', [53, 57, 58, 59, 60, 61, 74])
            ->update(['sede_id' => 1]);
    }
};
