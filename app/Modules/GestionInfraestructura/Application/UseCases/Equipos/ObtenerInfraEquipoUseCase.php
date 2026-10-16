<?php

namespace App\Modules\GestionInfraestructura\Application\UseCases\Equipos;

use App\Models\InfraEquipo;

class ObtenerInfraEquipoUseCase
{
    public function execute(int $id): InfraEquipo
    {
        return InfraEquipo::with([
            'sede',
            'area',
            'responsable.cargo',
            'responsable.sede',
            'proveedor',
            'trasladoDependencia',
            'trasladoArea',
            'creador',
            'mantenimientos.creador',
            'mantenimientos.coordinador',
            'mantenimientos.sede',
        ])->findOrFail($id);
    }
}
