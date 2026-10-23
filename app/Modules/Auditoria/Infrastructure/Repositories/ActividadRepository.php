<?php

namespace App\Modules\Auditoria\Infrastructure\Repositories;

use App\Models\Actividad;
use App\Modules\Auditoria\Application\DTOs\FiltroActividadesDTO;
use App\Modules\Auditoria\Domain\Contracts\ActividadRepositoryInterface;
use App\Modules\Auditoria\Domain\Entities\ActividadEntity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class ActividadRepository implements ActividadRepositoryInterface
{
    public function listarPaginado(FiltroActividadesDTO $filtro): LengthAwarePaginator
    {
        $query = Actividad::with(['usuario.rol', 'usuario.sede']);

        // Filtro por rango de fechas
        if (!empty($filtro->fechaInicio)) {
            $desde = strlen($filtro->fechaInicio) === 10 
                ? "{$filtro->fechaInicio} 00:00:00" 
                : $filtro->fechaInicio;
            $query->where('fecha', '>=', $desde);
        }

        if (!empty($filtro->fechaFin)) {
            $hasta = strlen($filtro->fechaFin) === 10 
                ? "{$filtro->fechaFin} 23:59:59" 
                : $filtro->fechaFin;
            $query->where('fecha', '<=', $hasta);
        }

        // Filtro por usuario específico
        if (!empty($filtro->usuarioId)) {
            $query->where('usuario_id', $filtro->usuarioId);
        }

        // Filtro por tabla afectada
        if (!empty($filtro->tablaAfectada)) {
            $query->where('tabla_afectada', $filtro->tablaAfectada);
        }

        // Búsqueda general por texto (acción, tabla o nombre/usuario del responsable)
        if (!empty($filtro->buscar)) {
            $term = $filtro->buscar;
            $query->where(function ($q) use ($term) {
                $q->where('accion', 'LIKE', "%{$term}%")
                  ->orWhere('tabla_afectada', 'LIKE', "%{$term}%")
                  ->orWhere('registro_id', $term)
                  ->orWhereHas('usuario', function ($qu) use ($term) {
                      $qu->where('nombre_completo', 'LIKE', "%{$term}%")
                         ->orWhere('usuario', 'LIKE', "%{$term}%")
                         ->orWhere('correo', 'LIKE', "%{$term}%");
                  });
            });
        }

        // Orden cronológico descendente (los más recientes primero)
        $query->orderBy('fecha', 'desc')->orderBy('id', 'desc');

        return $query->paginate($filtro->perPage, ['*'], 'page', $filtro->page);
    }

    public function obtenerPorId(int $id): ?ActividadEntity
    {
        $actividad = Actividad::with(['usuario.rol', 'usuario.sede'])->find($id);

        if (!$actividad) {
            return null;
        }

        return $this->mapToEntity($actividad);
    }

    public function obtenerEstadisticas(FiltroActividadesDTO $filtro): array
    {
        $query = Actividad::query();

        if (!empty($filtro->fechaInicio)) {
            $desde = strlen($filtro->fechaInicio) === 10 ? "{$filtro->fechaInicio} 00:00:00" : $filtro->fechaInicio;
            $query->where('fecha', '>=', $desde);
        }
        if (!empty($filtro->fechaFin)) {
            $hasta = strlen($filtro->fechaFin) === 10 ? "{$filtro->fechaFin} 23:59:59" : $filtro->fechaFin;
            $query->where('fecha', '<=', $hasta);
        }

        $totalRegistros = (clone $query)->count();
        $hoy = Carbon::today()->toDateString();
        $registrosHoy = Actividad::whereDate('fecha', $hoy)->count();

        // Top 5 tablas más afectadas
        $topTablas = Actividad::selectRaw('tabla_afectada, COUNT(*) as total')
            ->whereNotNull('tabla_afectada')
            ->where('tabla_afectada', '!=', '')
            ->groupBy('tabla_afectada')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->toArray();

        return [
            'total_registros' => $totalRegistros,
            'registros_hoy' => $registrosHoy,
            'top_tablas' => $topTablas,
        ];
    }

    public function obtenerTablasAfectadas(): array
    {
        return Actividad::distinct()
            ->whereNotNull('tabla_afectada')
            ->where('tabla_afectada', '!=', '')
            ->orderBy('tabla_afectada')
            ->pluck('tabla_afectada')
            ->toArray();
    }

    private function mapToEntity(Actividad $model): ActividadEntity
    {
        $usuario = null;
        if ($model->usuario) {
            $usuario = [
                'id' => $model->usuario->id,
                'nombre_completo' => $model->usuario->nombre_completo,
                'usuario' => $model->usuario->usuario,
                'correo' => $model->usuario->correo,
                'rol' => $model->usuario->rol ? $model->usuario->rol->nombre : null,
                'sede' => $model->usuario->sede ? $model->usuario->sede->nombre : null,
            ];
        }

        return ActividadEntity::fromArray([
            'id' => $model->id,
            'usuario_id' => $model->usuario_id,
            'accion' => $model->accion,
            'tabla_afectada' => $model->tabla_afectada,
            'registro_id' => $model->registro_id,
            'fecha' => $model->fecha ? $model->fecha->format('Y-m-d H:i:s') : null,
            'usuario' => $usuario,
        ]);
    }
}
