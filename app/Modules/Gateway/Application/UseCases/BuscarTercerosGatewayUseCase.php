<?php

namespace App\Modules\Gateway\Application\UseCases;

use App\Modules\Gateway\Domain\Contracts\GatewayRepositoryInterface;
use Illuminate\Support\Collection;

class BuscarTercerosGatewayUseCase
{
    public function __construct(protected GatewayRepositoryInterface $repository) {}

    public function execute(?string $nombre = null): Collection
    {
        $filtros = [];
        if ($nombre) {
            $filtros['nombre'] = $nombre;
        }

        return $this->repository->obtenerTerceros($filtros);
    }
}
