<?php

namespace App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos;

use App\Models\PcEquipo;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ObtenerCronogramaMantenimientosUseCase
{
    public function execute(?int $sedeId = null, ?string $tipoMantenimiento = null): array
    {
        $query = PcEquipo::with(['mantenimientos' => function($q) use ($tipoMantenimiento) {
            if ($tipoMantenimiento && !in_array($tipoMantenimiento, ['all', 'todos', 'todas', ''], true)) {
                $q->where('tipo_mantenimiento', $tipoMantenimiento);
            }
            $q->orderBy('fecha', 'desc');
        }, 'sede', 'area', 'responsable']);

        if ($sedeId) {
            $query->where('sede_id', $sedeId);
        }

        if ($tipoMantenimiento && !in_array($tipoMantenimiento, ['all', 'todos', 'todas', ''], true)) {
            $query->whereHas('mantenimientos', function($q) use ($tipoMantenimiento) {
                $q->where('tipo_mantenimiento', $tipoMantenimiento);
            });
        }

        $equipos = $query->get();

        $cronograma = $equipos->map(function($equipo) {
            $mantoInfo = $this->calculateMaintenanceInfo($equipo);
            
            // Determinar estado visual
            $estadoManto = 'al_dia';
            if ($mantoInfo['dias_restantes'] === null) {
                $estadoManto = 'sin_registro';
            } elseif ($mantoInfo['dias_restantes'] <= 0) {
                $estadoManto = 'vencido';
            } elseif ($mantoInfo['dias_restantes'] <= 30) {
                $estadoManto = 'proximo';
            }

            return [
                'id' => $equipo->id,
                'nombre_equipo' => $equipo->nombre_equipo,
                'numero_inventario' => $equipo->numero_inventario,
                'serial' => $equipo->serial,
                'marca' => $equipo->marca,
                'modelo' => $equipo->modelo,
                'tipo' => $equipo->tipo,
                'sede_id' => $equipo->sede_id,
                'sede' => $equipo->sede?->nombre,
                'area' => $equipo->area?->nombre,
                'responsable' => $equipo->responsable?->nombre_completo,
                'estado_equipo' => $equipo->estado,
                'mantenimiento' => array_merge($mantoInfo, ['estado_manto' => $estadoManto])
            ];
        });

        return $cronograma->toArray();
    }

    private function calculateMaintenanceInfo($equipo)
    {
        $config = DB::table('pc_config_cronograma')->first();
        
        $diasCumplimiento = 180; // Default 6 meses
        if ($config) {
            if ($config->dias_cumplimiento) {
                $diasCumplimiento = $config->dias_cumplimiento;
            } elseif ($config->meses_cumplimiento) {
                $diasCumplimiento = $config->meses_cumplimiento * 30;
            }
        }

        $ultimoMantenimiento = $equipo->mantenimientos->first();
        
        $fechaBase = null;
        $tipoBase = 'ninguna';

        if ($ultimoMantenimiento && $ultimoMantenimiento->fecha) {
            $fechaBase = Carbon::parse($ultimoMantenimiento->fecha);
            $tipoBase = 'ultimo_mantenimiento';
        } elseif ($equipo->fecha_ingreso) {
            $fechaBase = Carbon::parse($equipo->fecha_ingreso);
            $tipoBase = 'fecha_ingreso';
        }

        $diasRestantes = null;
        $fechaProximoManto = null;

        if ($fechaBase) {
            $fechaProximoManto = $fechaBase->copy()->addDays($diasCumplimiento);
            $diasRestantes = (int) now()->diffInDays($fechaProximoManto, false);
        }

        return [
            'dias_cumplimiento' => $diasCumplimiento,
            'dias_restantes' => $diasRestantes,
            'fecha_proximo_mantenimiento' => $fechaProximoManto?->toDateString(),
            'fecha_ultimo_mantenimiento' => $ultimoMantenimiento?->fecha,
            'base_calculo' => $tipoBase
        ];
    }
}
