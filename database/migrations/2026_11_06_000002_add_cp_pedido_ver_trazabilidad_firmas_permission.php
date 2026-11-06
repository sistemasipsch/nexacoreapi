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
        $permiso = DB::table('permisos')->where('nombre', 'cp_pedido.ver_trazabilidad_firmas')->first();

        if (!$permiso) {
            $permisoId = DB::table('permisos')->insertGetId([
                'nombre' => 'cp_pedido.ver_trazabilidad_firmas',
                'descripcion' => 'Ver trazabilidad y firmas de los pedidos de compra'
            ]);
        } else {
            $permisoId = $permiso->id;
        }

        // Roles que por defecto deben ver las firmas:
        // Administradores, compras, gerencia y roles con permisos de aprobación o gestión
        $roles = DB::table('rol')->get();

        foreach ($roles as $rol) {
            $hasApproval = DB::table('rol_permisos')
                ->join('permisos', 'rol_permisos.permiso_id', '=', 'permisos.id')
                ->where('rol_permisos.rol_id', $rol->id)
                ->whereIn('permisos.nombre', [
                    'cp_pedido.aprobar_compras',
                    'cp_pedido.aprobar_gerencia',
                    'cp_pedido.listar.compras',
                    'cp_pedido.listar_todos'
                ])
                ->exists();

            $isAdminOrMgmt = preg_match('/(ADMINISTRADOR|COMPRAS|GEREN)/i', $rol->nombre);

            if ($hasApproval || $isAdminOrMgmt) {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permiso = DB::table('permisos')->where('nombre', 'cp_pedido.ver_trazabilidad_firmas')->first();
        if ($permiso) {
            DB::table('rol_permisos')->where('permiso_id', $permiso->id)->delete();
            DB::table('permisos')->where('id', $permiso->id)->delete();
        }
    }
};
