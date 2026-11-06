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
        $permListar = DB::table('permisos')->where('nombre', 'cp_pedido.listar')->first();
        $permVer = DB::table('permisos')->where('nombre', 'cp_pedido.ver')->first();
        $permCrear = DB::table('permisos')->where('nombre', 'cp_pedido.crear')->first();

        if (!$permListar) {
            $permListarId = DB::table('permisos')->insertGetId([
                'nombre' => 'cp_pedido.listar',
                'descripcion' => 'Listar pedidos de compra propios o autorizados'
            ]);
        } else {
            $permListarId = $permListar->id;
        }

        if (!$permVer) {
            $permVerId = DB::table('permisos')->insertGetId([
                'nombre' => 'cp_pedido.ver',
                'descripcion' => 'Ver detalle de pedidos de compra'
            ]);
        } else {
            $permVerId = $permVer->id;
        }

        if (!$permCrear) {
            $permCrearId = DB::table('permisos')->insertGetId([
                'nombre' => 'cp_pedido.crear',
                'descripcion' => 'Crear nuevos pedidos de compra'
            ]);
        } else {
            $permCrearId = $permCrear->id;
        }

        // Buscar todos los roles del sistema que deban poder crear, listar o ver pedidos
        // (coordinadores, colaboradores, líderes, gerencias, etc.)
        $roles = DB::table('rol')->get();

        foreach ($roles as $rol) {
            // Asegurar que si tiene cp_pedido.crear, también tenga listar y ver
            $hasCrear = DB::table('rol_permisos')
                ->where('rol_id', $rol->id)
                ->where('permiso_id', $permCrearId)
                ->exists();

            $isCoordinadorOrStaff = preg_match('/(COORDINAD|COLABORADOR|LIDER|JEFE|PROFESIONAL|INGENIERO|ADMINISTRADOR|GEREN|AUXILIAR)/i', $rol->nombre);

            if ($hasCrear || $isCoordinadorOrStaff) {
                // Asignar cp_pedido.listar si no lo tiene
                $existsListar = DB::table('rol_permisos')
                    ->where('rol_id', $rol->id)
                    ->where('permiso_id', $permListarId)
                    ->exists();

                if (!$existsListar) {
                    DB::table('rol_permisos')->insert([
                        'rol_id' => $rol->id,
                        'permiso_id' => $permListarId,
                    ]);
                }

                // Asignar cp_pedido.ver si no lo tiene
                $existsVer = DB::table('rol_permisos')
                    ->where('rol_id', $rol->id)
                    ->where('permiso_id', $permVerId)
                    ->exists();

                if (!$existsVer) {
                    DB::table('rol_permisos')->insert([
                        'rol_id' => $rol->id,
                        'permiso_id' => $permVerId,
                    ]);
                }

                // Asignar cp_pedido.crear al rol COLABORADOR si no lo tiene
                if (stripos($rol->nombre, 'COLABORADOR') !== false && !$hasCrear) {
                    DB::table('rol_permisos')->insert([
                        'rol_id' => $rol->id,
                        'permiso_id' => $permCrearId,
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
        // No destructivo
    }
};
