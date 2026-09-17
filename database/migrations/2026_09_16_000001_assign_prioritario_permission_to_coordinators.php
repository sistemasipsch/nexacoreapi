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
        // Verificar o crear el permiso 'cp_pedido.realizar_pedido_prioritario'
        $permiso = DB::table('permisos')->where('nombre', 'cp_pedido.realizar_pedido_prioritario')->first();

        if (!$permiso) {
            $permisoId = DB::table('permisos')->insertGetId([
                'nombre' => 'cp_pedido.realizar_pedido_prioritario',
                'descripcion' => 'Permite crear pedidos prioritarios al sistema a cualquier hora',
            ]);
        } else {
            $permisoId = $permiso->id;
        }

        // Buscar todos los roles de coordinadores, administradores y gerencia
        $roles = DB::table('rol')
            ->where(function ($query) {
                $query->where('nombre', 'LIKE', '%COORDINAD%')
                      ->orWhere('nombre', 'LIKE', '%COORDINAC%')
                      ->orWhere('nombre', 'LIKE', '%ADMINISTRADOR%')
                      ->orWhere('nombre', 'LIKE', '%GERENTE%');
            })
            ->get();

        foreach ($roles as $rol) {
            $exists = DB::table('rol_permisos')
                ->where('rol_id', $rol->id)
                ->where('permiso_id', $permisoId)
                ->exists();

            if (!$exists) {
                DB::table('rol_permisos')->insert([
                    'rol_id' => $rol->id,
                    'permiso_id' => $permisoId,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revertir asignaciones masivas para evitar pérdida accidental de accesos
    }
};
