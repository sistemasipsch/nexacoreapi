<?php

namespace App\Modules\Gateway\Application\UseCases;

use App\Modules\Gateway\Domain\Contracts\GatewayRepositoryInterface;
use Illuminate\Support\Collection;

class BuscarArticulosGatewayUseCase
{
    public function __construct(protected GatewayRepositoryInterface $repository) {}

    /**
     * Busca artículos en el sistema externo.
     * Aquí se puede agregar lógica en un futuro (ej. comparar con la BD local).
     *
     * @param string|null $termino
     * @return Collection
     */
    public function execute(?string $termino = null): Collection
    {
        $filtros = [];
        if ($termino) {
            $filtros['termino'] = $termino;
        }

        return $this->repository->obtenerArticulos($filtros);
    }
}
