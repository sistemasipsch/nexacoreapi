<?php
namespace App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento;

use App\Models\Mantenimiento;
use App\Models\AgendaMantenimiento;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class ObtenerEstadisticasMantenimientoUseCase
{
    /** Usuarios excluidos del ranking de Top Creadores. */
    private const CREADORES_EXCLUIDOS = [
        'Junior Edimer Arias Castellanos',
        'ASHLY NICOLE PEREZ LOPEZ',
    ];

    /** Únicos técnicos que se muestran en Carga de Trabajo Técnica. */
    private const TECNICOS = [
        'Jaime Alexis Peña',
        'JHON HELBER MONTEALEGRE ZUÑIGA',
    ];

    public function execute()
    {
        // Usuarios que no deben aparecer en el ranking de creadores
        $topCreators = Mantenimiento::select('mantenimientos.creado_por', DB::raw('count(mantenimientos.id) as total'))
            ->whereNotNull('mantenimientos.creado_por')
            ->whereNotIn('mantenimientos.creado_por', function ($q) {
                $q->select('id')->from('usuarios')->whereIn('nombre_completo', self::CREADORES_EXCLUIDOS);
            })
            ->with('creador:id,nombre_completo')
            ->groupBy('mantenimientos.creado_por')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        // Distribución por sede: las sedes se agrupan por id; los registros sin sede quedan en un solo grupo
        $bySede = Mantenimiento::select('mantenimientos.sede_id', DB::raw('count(mantenimientos.id) as total'))
            ->with('sede:id,nombre')
            ->groupBy('mantenimientos.sede_id')
            ->orderBy('total', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'sede_id' => $item->sede_id,
                    'sede' => $item->sede ? ['id' => $item->sede->id, 'nombre' => $item->sede->nombre] : null,
                    'total' => (int) $item->total,
                ];
            })
            ->values();

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

        // Carga de trabajo: solo los técnicos oficiales (se muestran aunque no tengan agendamientos)
        $now = now();
        $technicianWorkload = Usuario::whereIn('nombre_completo', self::TECNICOS)
            ->select('id', 'nombre_completo')
            ->get()
            ->map(function ($tecnico) use ($now) {
                $agendas = AgendaMantenimiento::where('tecnico_id', $tecnico->id);
                return [
                    'tecnico_id' => $tecnico->id,
                    'tecnico' => ['id' => $tecnico->id, 'nombre_completo' => $tecnico->nombre_completo],
                    'total' => (clone $agendas)->count(),
                    'programados' => (clone $agendas)->where('fecha_inicio', '>', $now)->count(),
                    'en_curso' => (clone $agendas)->where('fecha_inicio', '<=', $now)->where('fecha_fin', '>=', $now)->count(),
                    'finalizados' => (clone $agendas)->where('fecha_fin', '<', $now)->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();
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