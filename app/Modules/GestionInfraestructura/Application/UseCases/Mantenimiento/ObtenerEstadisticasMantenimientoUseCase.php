<?php
namespace App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento;

use App\Models\Mantenimiento;
use App\Models\AgendaMantenimiento;
use Illuminate\Support\Facades\DB;

class ObtenerEstadisticasMantenimientoUseCase
{
    public function execute()
    {
        $topCreators = Mantenimiento::select('mantenimientos.creado_por', DB::raw('count(mantenimientos.id) as total'))
            ->whereNotNull('mantenimientos.creado_por')
            ->with('creador:id,nombre_completo')
            ->groupBy('mantenimientos.creado_por')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        $bySede = Mantenimiento::select('mantenimientos.sede_id', DB::raw('count(mantenimientos.id) as total'))
            ->with('sede:id,nombre')
            ->groupBy('mantenimientos.sede_id')
            ->get();

        $reviewStatus = Mantenimiento::select('mantenimientos.esta_revisado', DB::raw('count(mantenimientos.id) as total'))
            ->groupBy('mantenimientos.esta_revisado')
            ->get()
            ->map(function ($item) {
                return [
                    'label' => $item->esta_revisado ? 'Revisados' : 'Pendientes',
                    'total' => $item->total,
                    'value' => (bool)$item->esta_revisado
                ];
            });

        $technicianWorkload = AgendaMantenimiento::select('agenda_mantenimientos.tecnico_id', DB::raw('count(agenda_mantenimientos.id) as total'))
            ->whereNotNull('agenda_mantenimientos.tecnico_id')
            ->with('tecnico:id,nombre_completo')
            ->groupBy('agenda_mantenimientos.tecnico_id')
            ->orderBy('total', 'desc')
            ->get();

        $monthlyTrends = Mantenimiento::select(
                DB::raw("DATE_FORMAT(mantenimientos.fecha_creacion, '%Y-%m') as mes"),
                DB::raw('count(mantenimientos.id) as total')
            )
            ->whereNotNull('fecha_creacion')
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        $totalMantenimientos = Mantenimiento::count();

        $totalPendientes = Mantenimiento::where('esta_revisado', false)->count();

        $totalAgendados = AgendaMantenimiento::count();

        return [
            'summary' => [
                'total_mantenimientos' => $totalMantenimientos,
                'total_pendientes' => $totalPendientes,
                'total_agendados' => $totalAgendados,
            ],
            'top_creators' => $topCreators,
            'by_sede' => $bySede,
            'review_status' => $reviewStatus,
            'technician_workload' => $technicianWorkload,
            'monthly_trends' => $monthlyTrends,
        ];
    }
}