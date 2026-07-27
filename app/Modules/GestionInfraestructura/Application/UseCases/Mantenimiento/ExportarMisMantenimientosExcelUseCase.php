<?php
namespace App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento;

use App\Models\Mantenimiento;

class ExportarMisMantenimientosExcelUseCase
{
    public function execute($fechaInicio, $fechaFin, $user, $export)
    {
        $query = Mantenimiento::with(['sede', 'coordinador', 'revisador', 'creador', 'agendas.tecnico']);

        // Filtrar obligatoriamente por el usuario que solicita (creado_por = id del usuario)
        $query->where('creado_por', $user->id);

        if ($fechaInicio) {
            $query->whereDate('fecha_creacion', '>=', $fechaInicio);
        }
        if ($fechaFin) {
            $query->whereDate('fecha_creacion', '<=', $fechaFin);
        }

        $maintenances = $query->orderBy('fecha_creacion', 'desc')->get();

        return $export->generate($maintenances, $user);
    }
}
