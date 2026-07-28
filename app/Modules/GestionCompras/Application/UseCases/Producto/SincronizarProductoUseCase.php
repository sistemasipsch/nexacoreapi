<?php

namespace App\Modules\GestionCompras\Application\UseCases\Producto;

use App\Modules\GestionCompras\Infrastructure\Repositories\CpProductoRepository;
use App\Modules\Gateway\Application\UseCases\BuscarArticulosGatewayUseCase;
use Exception;

class SincronizarProductoUseCase
{
    public function __construct(
        protected CpProductoRepository $repository,
        protected BuscarArticulosGatewayUseCase $buscarGatewayUseCase
    ) {}

    public function execute(string $codigo)
    {
        // 1. Buscamos el artículo en el Gateway por su código
        $articulos = $this->buscarGatewayUseCase->execute($codigo);

        // 2. Filtramos para asegurarnos de que el código coincida exactamente
        $articuloExterno = $articulos->firstWhere('codigo', $codigo);

        if (!$articuloExterno) {
            throw new Exception("El producto con código {$codigo} no fue encontrado en el sistema externo.");
        }

        // 3. Sincronizamos en la BD local (si existe, actualiza el nombre; si no, lo crea)
        $producto = $this->repository->updateOrCreateByCodigo($codigo, [
            'nombre' => $articuloExterno->nombre
        ]);

        return $producto;
    }
}
