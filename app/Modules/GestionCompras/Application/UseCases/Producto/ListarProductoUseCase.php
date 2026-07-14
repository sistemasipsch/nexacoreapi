<?php

namespace App\Modules\GestionCompras\Application\UseCases\Producto;

use App\Modules\GestionCompras\Infrastructure\Repositories\CpProductoRepository;

class ListarProductoUseCase
{
    public function __construct(protected CpProductoRepository $repository) {}

    public function execute(?string $search = null)
    {
        return $this->repository->getAll($search);
    }
}