<?php
namespace App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento;

use App\Models\Mantenimiento;
use App\Modules\GestionInfraestructura\Application\DTOs\Mantenimiento\FiltroMantenimientosDTO;

class ListarMantenimientosUseCase
{
    protected $relations = ['sede', 'coordinador', 'revisador', 'creador'];

    public function execute(?FiltroMantenimientosDTO $dto = null)
    {
        $query = Mantenimiento::with($this->relations);

        if ($dto) {
            if ($dto->fecha_inicio) {
                $query->whereDate('fecha_creacion', '>=', $dto->fecha_inicio);
            }
            if ($dto->fecha_fin) {
                $query->whereDate('fecha_creacion', '<=', $dto->fecha_fin);
            }
            if ($dto->sede_id) {
                $query->where('sede_id', $dto->sede_id);
            }
            if ($dto->tecnico) {
                $query->whereHas('creador', function ($q) use ($dto) {
                    $q->where('nombre_completo', 'like', '%' . $dto->tecnico . '%');
                });
            }

            return $query->orderBy('fecha_creacion', 'desc')
                         ->paginate($dto->limit, ['*'], 'page', $dto->page);
        }

        return $query->orderBy('fecha_creacion', 'desc')->get();
    }
}