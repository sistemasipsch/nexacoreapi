<?php

namespace App\Modules\Autenticacion\Application\UseCases\Usuario;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class EliminarUsuarioUseCase
{
    /**
     * Elimina físicamente un usuario si no tiene datos operativos o lo desactiva si se solicita.
     *
     * @param int|string $id
     * @param bool $forceDeactivate
     * @return array
     * @throws \Exception
     */
    public function execute($id, bool $forceDeactivate = false): array
    {
        $usuario = Usuario::findOrFail($id);

        $currentAuthId = auth('api')->id() ?? auth()->id();
        if ($currentAuthId && (int)$currentAuthId === (int)$id) {
            throw new \Exception('No puedes eliminar tu propia cuenta de usuario en sesión.');
        }

        // Analizar registros operativos vinculados a nombre de este usuario
        $operationalDetails = [];

        // 1. Pedidos de Compras
        if (Schema::hasTable('cp_pedidos')) {
            $pedidos = DB::table('cp_pedidos')
                ->where('elaborado_por', $id)
                ->orWhere('proceso_compra', $id)
                ->orWhere('responsable_aprobacion', $id)
                ->count();
            if ($pedidos > 0) {
                $operationalDetails[] = "{$pedidos} pedido(s) de compras";
            }
        }

        // 2. Pedidos Programados
        if (Schema::hasTable('cp_pedidos_programados')) {
            $pedProg = DB::table('cp_pedidos_programados')->where('creado_por', $id)->count();
            if ($pedProg > 0) {
                $operationalDetails[] = "{$pedProg} pedido(s) programado(s)";
            }
        }

        // 3. Mantenimientos
        if (Schema::hasTable('mantenimientos')) {
            $mantenimientos = DB::table('mantenimientos')
                ->where('creado_por', $id)
                ->orWhere('revisado_por', $id)
                ->orWhere('coordinador_id', $id)
                ->count();
            if ($mantenimientos > 0) {
                $operationalDetails[] = "{$mantenimientos} registro(s) de mantenimiento";
            }
        }

        // 4. Agenda de Mantenimientos (Técnico / Coordinador)
        if (Schema::hasTable('agenda_mantenimientos')) {
            $agenda = DB::table('agenda_mantenimientos')
                ->where('tecnico_id', $id)
                ->orWhere('coordinador_id', $id)
                ->count();
            if ($agenda > 0) {
                $operationalDetails[] = "{$agenda} cita(s) en agenda de mantenimientos";
            }
        }

        // 5. Inventario General
        if (Schema::hasTable('inventario')) {
            $inv = DB::table('inventario')->where('creado_por', $id)->count();
            if ($inv > 0) {
                $operationalDetails[] = "{$inv} ítem(s) de inventario general";
            }
        }

        // 6. Inventario de Infraestructura
        if (Schema::hasTable('inventario_infraestructura')) {
            $invInfra = DB::table('inventario_infraestructura')->where('creado_por', $id)->count();
            if ($invInfra > 0) {
                $operationalDetails[] = "{$invInfra} equipo(s) de infraestructura";
            }
        }

        // 7. Equipos de Sistemas
        if (Schema::hasTable('pc_equipos')) {
            $pcEquipos = DB::table('pc_equipos')->where('creado_por', $id)->count();
            if ($pcEquipos > 0) {
                $operationalDetails[] = "{$pcEquipos} equipo(s) de cómputo/sistemas";
            }
        }

        // 8. Mantenimientos de Sistemas
        if (Schema::hasTable('pc_mantenimientos')) {
            $pcMaint = DB::table('pc_mantenimientos')->where('creado_por', $id)->count();
            if ($pcMaint > 0) {
                $operationalDetails[] = "{$pcMaint} mantenimiento(s) de sistemas";
            }
        }

        // 9. Reportes de Fallas
        if (Schema::hasTable('rf_reportes_fallas')) {
            $fallas = DB::table('rf_reportes_fallas')
                ->where('usuario_id', $id)
                ->orWhere('responsable_id', $id)
                ->count();
            if ($fallas > 0) {
                $operationalDetails[] = "{$fallas} reporte(s) de fallas";
            }
        }

        // 10. Comentarios en Reportes de Fallas
        if (Schema::hasTable('rf_reportes_comentarios')) {
            $comentariosFallas = DB::table('rf_reportes_comentarios')->where('usuario_id', $id)->count();
            if ($comentariosFallas > 0) {
                $operationalDetails[] = "{$comentariosFallas} comentario(s) de fallas";
            }
        }

        // 11. Buzón de Sugerencias
        if (Schema::hasTable('buzon_sugerencia')) {
            $buzon = DB::table('buzon_sugerencia')
                ->where('creado_por', $id)
                ->orWhere('asignado_a', $id)
                ->count();
            if ($buzon > 0) {
                $operationalDetails[] = "{$buzon} sugerencia(s)";
            }
        }

        // 12. Comentarios en Sugerencias
        if (Schema::hasTable('sugerencia_comentarios')) {
            $sugCom = DB::table('sugerencia_comentarios')->where('usuario_id', $id)->count();
            if ($sugCom > 0) {
                $operationalDetails[] = "{$sugCom} comentario(s) en sugerencias";
            }
        }

        // 13. Imagen Mensual
        if (Schema::hasTable('imagen_mensual')) {
            $imagenes = DB::table('imagen_mensual')->where('subido_por', $id)->count();
            if ($imagenes > 0) {
                $operationalDetails[] = "{$imagenes} imagen(es) mensual(es)";
            }
        }

        $hasOperationalRecords = count($operationalDetails) > 0;

        // Si tiene registros operativos a su nombre
        if ($hasOperationalRecords) {
            if ($forceDeactivate) {
                $usuario->update(['estado' => 0]);
                return [
                    'status' => 'desactivado',
                    'action' => 'deactivated',
                    'mensaje' => 'El usuario posee registros históricos y ha sido desactivado exitosamente para revocar su acceso.'
                ];
            }

            $detallesStr = implode(', ', $operationalDetails);
            throw new \Exception("No es posible eliminar el usuario permanentemente porque tiene registros asociados a su nombre: {$detallesStr}. Puede desactivar su cuenta para revocar su acceso sin alterar la trazabilidad del sistema.");
        }

        // Si no tiene registros operativos, realizar eliminación limpia atómica
        return DB::transaction(function () use ($usuario, $id) {
            if (Schema::hasTable('sec_intentos_login')) {
                DB::table('sec_intentos_login')->where('id_usuario', $id)->delete();
            }
            if (Schema::hasTable('sec_codigo_verificacion')) {
                DB::table('sec_codigo_verificacion')->where('id_usuario', $id)->delete();
            }
            if (Schema::hasTable('sec_contrasenas_anteriores')) {
                DB::table('sec_contrasenas_anteriores')->where('id_usuario', $id)->delete();
            }
            if (Schema::hasTable('sec_auditoria')) {
                DB::table('sec_auditoria')->where('id_usuario', $id)->delete();
            }
            if (Schema::hasTable('notif_notificaciones')) {
                DB::table('notif_notificaciones')->where('id_usuario', $id)->delete();
            }
            if (Schema::hasTable('actividades')) {
                DB::table('actividades')->where('usuario_id', $id)->delete();
            }

            // Limpieza de archivos multimedia en storage
            $foto = $usuario->getRawOriginal('foto_usuario');
            if ($foto) {
                $cleanFoto = ltrim(str_replace(['storage/', 'public/', 'api/'], '', $foto), '/');
                Storage::disk('public')->delete($cleanFoto);
            }

            $firma = $usuario->getRawOriginal('firma_digital');
            if ($firma && !str_starts_with($firma, 'http://') && !str_starts_with($firma, 'https://')) {
                $cleanFirma = ltrim(str_replace(['storage/', 'public/', 'api/'], '', $firma), '/');
                Storage::disk('public')->delete($cleanFirma);
            }

            $usuario->delete();

            return [
                'status' => 'eliminado',
                'action' => 'deleted',
                'mensaje' => 'Usuario eliminado exitosamente'
            ];
        });
    }
}
