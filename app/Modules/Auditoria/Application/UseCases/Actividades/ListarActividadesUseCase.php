<?php

namespace App\Modules\Auditoria\Application\UseCases\Actividades;

use App\Modules\Auditoria\Application\DTOs\FiltroActividadesDTO;
use App\Modules\Auditoria\Domain\Contracts\ActividadRepositoryInterface;

class ListarActividadesUseCase
{
    public function __construct(
        private readonly ActividadRepositoryInterface $repository
    ) {}

    public function execute(FiltroActividadesDTO $filtro): array
    {
        $paginator = $this->repository->listarPaginado($filtro);

        return [
            'items' => $paginator->items(),
            'paginacion' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'tablas_disponibles' => $this->repository->obtenerTablasAfectadas(),
        ];
    }
}
