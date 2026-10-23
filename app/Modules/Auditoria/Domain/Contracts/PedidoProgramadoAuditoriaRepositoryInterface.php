<?php

namespace App\Modules\Auditoria\Domain\Contracts;

use App\Modules\Auditoria\Application\DTOs\FiltroPedidosProgramadosDTO;
use App\Modules\Auditoria\Domain\Entities\PedidoProgramadoAuditoriaEntity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PedidoProgramadoAuditoriaRepositoryInterface
{
    /**
     * Lista pedidos programados de forma paginada aplicando filtros.
     */
    public function listarPaginado(FiltroPedidosProgramadosDTO $filtro): LengthAwarePaginator;

    /**
     * Obtiene un pedido progamado por su ID.
     */
    public function obtenerPorId(int $id): ?PedidoProgramadoAuditoriaEntity;
}
