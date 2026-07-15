<?php

namespace App\Modules\GestionCompras\Application\UseCases\Producto;

use App\Modules\GestionCompras\Infrastructure\Repositories\CpProductoRepository;

class ListarTodosProductosUseCase
{
    public function __construct(protected CpProductoRepository $repository) {}

    public function execute()
    {
        return $this->repository->getAllWithoutLimit();
    }
}
