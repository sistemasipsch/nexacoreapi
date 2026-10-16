<?php
namespace App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento;

use App\Models\Mantenimiento;

class ObtenerMantenimientoUseCase
{
    protected $relations = ['sede', 'coordinador', 'revisador', 'creador', 'equipoInfraestructura'];

    public function execute($id)
    {
        return Mantenimiento::with($this->relations)->find($id);
    }
}