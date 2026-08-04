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
            // Se envía como 'q' (y 'nombre' por compatibilidad) para que el API busque en todos los nombres y apellidos
            $filtros['q'] = $nombre;
            $filtros['nombre'] = $nombre;
        }

        return $this->repository->obtenerTerceros($filtros);
    }
}
