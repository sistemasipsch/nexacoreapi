<?php

namespace App\Modules\Auditoria\Application\UseCases\Actividades;

use App\Modules\Auditoria\Application\DTOs\FiltroActividadesDTO;
use App\Modules\Auditoria\Domain\Contracts\ActividadRepositoryInterface;

class ObtenerEstadisticasActividadesUseCase
{
    public function __construct(
        private readonly ActividadRepositoryInterface $repository
    ) {}

    public function execute(FiltroActividadesDTO $filtro): array
    {
        return $this->repository->obtenerEstadisticas($filtro);
    }
}
