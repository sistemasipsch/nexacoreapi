<?php

namespace App\Modules\Gateway\Domain\Contracts;

use Illuminate\Support\Collection;

interface GatewayRepositoryInterface
{
    /**
     * Obtiene una lista de artículos (productos/servicios) desde el Gateway.
     *
     * @param array $filtros
     * @return Collection
     */
    public function obtenerArticulos(array $filtros = []): Collection;

    /**
     * Obtiene una lista de terceros (pacientes, proveedores, personal) desde el Gateway.
     *
     * @param array $filtros
     * @return Collection
     */
    public function obtenerTerceros(array $filtros = []): Collection;
}
