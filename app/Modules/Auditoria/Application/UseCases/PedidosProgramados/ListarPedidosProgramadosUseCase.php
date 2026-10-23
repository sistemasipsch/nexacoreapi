<?php

namespace App\Modules\Auditoria\Application\UseCases\PedidosProgramados;

use App\Modules\Auditoria\Application\DTOs\FiltroPedidosProgramadosDTO;
use App\Modules\Auditoria\Domain\Contracts\PedidoProgramadoAuditoriaRepositoryInterface;

class ListarPedidosProgramadosUseCase
{
    public function __construct(
        private readonly PedidoProgramadoAuditoriaRepositoryInterface $repository
    ) {}

    public function execute(FiltroPedidosProgramadosDTO $filtro): array
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
            'estados_disponibles' => [
                'programado',
                'ejecutado',
                'cancelado',
                'error'
            ],
        ];
    }
}
