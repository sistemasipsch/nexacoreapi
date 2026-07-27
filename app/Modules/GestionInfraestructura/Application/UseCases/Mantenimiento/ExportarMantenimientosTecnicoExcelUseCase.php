<?php
namespace App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento;

use App\Models\Mantenimiento;

class ExportarMantenimientosTecnicoExcelUseCase
{
    public function execute($fechaInicio, $fechaFin, $tecnicoId, $user, $export)
    {
        $query = Mantenimiento::with(['sede', 'coordinador', 'revisador', 'creador', 'agendas.tecnico']);

        // Filtrar por el técnico en sus agendas
        $query->whereHas('agendas', function ($q) use ($tecnicoId) {
            $q->where('tecnico_id', $tecnicoId);
        });

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
