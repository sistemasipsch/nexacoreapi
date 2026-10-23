<?php

namespace App\Modules\Auditoria\Application\UseCases\Actividades;

use App\Modules\Auditoria\Domain\Contracts\ActividadRepositoryInterface;
use App\Modules\Auditoria\Domain\Entities\ActividadEntity;
use Exception;

class ObtenerActividadUseCase
{
    public function __construct(
        private readonly ActividadRepositoryInterface $repository
    ) {}

    public function execute(int $id): ActividadEntity
    {
        $actividad = $this->repository->obtenerPorId($id);

        if (!$actividad) {
            throw new Exception("El registro de actividad con ID {$id} no fue encontrado.", 404);
        }

        return $actividad;
    }
}
