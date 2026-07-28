<?php

namespace App\Modules\GestionCompras\Application\UseCases\Producto;

use App\Modules\GestionCompras\Infrastructure\Repositories\CpProductoRepository;

class ListarProductoUseCase
{
    public function __construct(protected CpProductoRepository $repository) {}

    public function execute(?string $search = null, int $perPage = 20)
    {
        return $this->repository->getAll($search, $perPage);
    }
}