<?php

namespace App\Modules\Auditoria\Application\UseCases\PedidosProgramados;

use App\Modules\Auditoria\Domain\Contracts\PedidoProgramadoAuditoriaRepositoryInterface;
use App\Modules\Auditoria\Domain\Entities\PedidoProgramadoAuditoriaEntity;
use Exception;

class ObtenerPedidoProgramadoUseCase
{
    public function __construct(
        private readonly PedidoProgramadoAuditoriaRepositoryInterface $repository
    ) {}

    public function execute(int $id): PedidoProgramadoAuditoriaEntity
    {
        $pedido = $this->repository->obtenerPorId($id);

        if (!$pedido) {
            throw new Exception("El pedido programado con ID {$id} no fue encontrado.", 404);
        }

        return $pedido;
    }
}
