<?php

namespace App\Modules\GestionSistemas\Infrastructure\Repositories;

use App\Models\PcEquipo;
use App\Models\PcDevuelto;
use App\Modules\GestionSistemas\Domain\Contracts\PcEquipoRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PcEquipoRepository implements PcEquipoRepositoryInterface
{
    public function create(array $data): PcEquipo
    {
        return PcEquipo::create($data);
    }

    public function find(int $id): ?PcEquipo
    {
        return PcEquipo::with(['sede', 'area', 'responsable'])->find($id);
    }

    public function update(int $id, array $data): ?PcEquipo
    {
        $equipo = PcEquipo::find($id);
        if ($equipo) {
            $equipo->update($data);
            return $equipo;
        }
        return null;
    }

    public function delete(int $id): bool
    {
        $equipo = PcEquipo::find($id);
        if ($equipo) {
            DB::beginTransaction();
            try {
                // Delete children of entregas
                $entregaIds = DB::table('pc_entregas')->where('equipo_id', $id)->pluck('id');
                if ($entregaIds->isNotEmpty()) {
                    DB::table('pc_devuelto')->whereIn('entrega_id', $entregaIds)->delete();
                    DB::table('pc_perifericos_entregados')->whereIn('entrega_id', $entregaIds)->delete();
                }

                // Delete direct children of pc_equipos
                DB::table('pc_entregas')->where('equipo_id', $id)->delete();
                DB::table('pc_caracteristicas_tecnicas')->where('equipo_id', $id)->delete();
                DB::table('pc_cronograma_mantenimientos')->where('equipo_id', $id)->delete();
                DB::table('pc_historial_asignaciones')->where('equipo_id', $id)->delete();
                DB::table('pc_licencias_software')->where('equipo_id', $id)->delete();
                DB::table('pc_mantenimientos')->where('equipo_id', $id)->delete();

                // Finally delete the parent record
                $equipo->delete();
                
                DB::commit();
                return true;
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }
        return false;
    }

    public function buscar(string $query)
    {
        return PcEquipo::with(['sede', 'area', 'responsable'])
            ->where('nombre_equipo', 'like', "%{$query}%")
            ->orWhere('serial', 'like', "%{$query}%")
            ->orWhere('numero_inventario', 'like', "%{$query}%")
            ->limit(10)
            ->get();
    }

    public function getHojaVidaCompleta(int $id): ?array
    {
        $equipo = PcEquipo::with([
            'sede',
            'area', 
            'responsable:id,nombre',
            'creador:id,nombre_completo',
            'caracteristicasTecnicas',
            'licenciasSoftware',
            'entregas' => function ($q) {
                $q->with(['funcionario'])->orderBy('fecha_entrega', 'desc');
            },
            'mantenimientos' => function ($q) {
                $q->with(['empresaResponsable', 'creador:id,nombre_completo'])->orderBy('fecha', 'desc');
            },
        ])->find($id);

        if (!$equipo) {
            return null;
        }

        // Load devuelto for each entrega
        $equipo->entregas->each(function ($entrega) {
            $entrega->load('perifericos.inventario');
            $devuelto = PcDevuelto::where('entrega_id', $entrega->id)->first();
            $entrega->setAttribute('devolucion', $devuelto);
        });

        if ($equipo->creador) {
            $equipo->creador->makeHidden(['is_online', 'activity_status']);
        }

        $equipo->mantenimientos->each(function ($mantenimiento) {
            if ($mantenimiento->creador) {
                $mantenimiento->creador->makeHidden(['is_online', 'activity_status']);
            }
        });

        $mantoInfo = $this->calculateMaintenanceInfo($equipo);

        return [
            'equipo' => $equipo,
            'mantenimiento_config' => $mantoInfo,
        ];
    }

    /**
     * Calcula la información de mantenimiento basado en el cronograma y el historial.
     */
    private function calculateMaintenanceInfo($equipo)
    {
        $config = DB::table('pc_config_cronograma')->first();
        
        // Calcular días de cumplimiento
        $diasCumplimiento = 180; // Default 6 meses
        if ($config) {
            if ($config->dias_cumplimiento) {
                $diasCumplimiento = $config->dias_cumplimiento;
            } elseif ($config->meses_cumplimiento) {
                $diasCumplimiento = $config->meses_cumplimiento * 30;
            }
        }

        // Determinar fecha base (último mantenimiento o fecha de ingreso)
        $ultimoMantenimiento = $equipo->mantenimientos->first(); // Ya vienen ordenados desc en hojaDeVida
        
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
