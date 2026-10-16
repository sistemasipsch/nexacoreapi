<?php

namespace App\Modules\GestionInfraestructura\Application\UseCases\Equipos;

use App\Models\InfraEquipo;
use App\Modules\GestionInfraestructura\Application\DTOs\Equipos\FiltroInfraEquiposDTO;

class ListarInfraEquiposUseCase
{
    public function execute(FiltroInfraEquiposDTO $filtro)
    {
        $query = InfraEquipo::with([
            'sede',
            'area',
            'responsable.cargo',
            'proveedor',
            'trasladoDependencia',
            'trasladoArea',
            'creador',
        ])->withCount('mantenimientos');

        if (!empty($filtro->q)) {
            $term = trim($filtro->q);
            $query->where(function ($q) use ($term) {
                $q->where('nombre_equipo', 'LIKE', "%{$term}%")
                  ->orWhere('serial', 'LIKE', "%{$term}%")
                  ->orWhere('activo_fijo', 'LIKE', "%{$term}%")
                  ->orWhere('marca', 'LIKE', "%{$term}%")
                  ->orWhere('modelo', 'LIKE', "%{$term}%")
                  ->orWhere('tipo_equipo', 'LIKE', "%{$term}%")
                  ->orWhere('ubicacion_especifica', 'LIKE', "%{$term}%");
            });
        }

        if (!empty($filtro->sede_id)) {
            $query->where('sede_id', $filtro->sede_id);
        }

        if (!empty($filtro->area_id)) {
            $query->where('area_id', $filtro->area_id);
        }

        if (!empty($filtro->tipo_equipo)) {
            $tipo = trim($filtro->tipo_equipo);
            $query->where(function ($q) use ($tipo) {
                $q->where('tipo_equipo', $tipo)
                  ->orWhere('tipo_equipo', 'LIKE', "{$tipo}%");

                if ($tipo === 'IMC') {
                    $q->orWhere('tipo_equipo', 'LIKE', '%Aire%')
                      ->orWhere('tipo_equipo', 'LIKE', '%Minisplit%')
                      ->orWhere('tipo_equipo', 'LIKE', '%Climat%');
                } elseif ($tipo === 'MAQ') {
                    $q->orWhere('tipo_equipo', 'LIKE', '%Planta%')
                      ->orWhere('tipo_equipo', 'LIKE', '%Bomba%')
                      ->orWhere('tipo_equipo', 'LIKE', '%Transformador%')
                      ->orWhere('tipo_equipo', 'LIKE', '%UPS%');
                } elseif ($tipo === 'EC') {
                    $q->orWhere('tipo_equipo', 'LIKE', '%Comput%')
                      ->orWhere('tipo_equipo', 'LIKE', '%Impresora%');
                } elseif ($tipo === 'EB') {
                    $q->orWhere('tipo_equipo', 'LIKE', '%Biom%');
                } elseif ($tipo === 'ME') {
                    $q->orWhere('tipo_equipo', 'LIKE', '%Mobil%')
                      ->orWhere('tipo_equipo', 'LIKE', '%Enser%');
                }
            });
        }

        if (!empty($filtro->estado_equipo)) {
            $query->where('estado_equipo', $filtro->estado_equipo);
        }

        return $query->orderBy('id', 'desc')->paginate($filtro->per_page);
    }
}
