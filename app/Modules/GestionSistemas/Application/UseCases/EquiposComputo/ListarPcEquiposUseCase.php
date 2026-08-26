<?php

namespace App\Modules\GestionSistemas\Application\UseCases\EquiposComputo;

use App\Models\PcEquipo;

class ListarPcEquiposUseCase
{
    public function execute(?string $search = null, ?int $sedeId = null)
    {
        $query = PcEquipo::select([
            'id', 'nombre_equipo', 'marca', 'modelo', 'serial', 'tipo', 
            'numero_inventario', 'ip_fija', 'estado', 'imagen_url', 
            'sede_id', 'area_id', 'responsable_id', 'creado_por'
        ])->with([
            'sede:id,nombre', 
            'area:id,nombre', 
            'responsable:id,nombre', 
            'creador:id,nombre_completo'
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('serial', 'like', "%{$search}%")
                    ->orWhere('marca', 'like', "%{$search}%")
                    ->orWhere('modelo', 'like', "%{$search}%")
                    ->orWhere('numero_inventario', 'like', "%{$search}%");
            });
        }

        if ($sedeId) {
            $query->where('sede_id', $sedeId);
        }

        $equipos = $query->orderBy('id', 'desc')->get();

        $equipos->each(function ($equipo) {
            if ($equipo->creador) {
                $equipo->creador->makeHidden(['is_online', 'activity_status', 'firma_digital']);
            }
        });

        return $equipos;
    }
}
