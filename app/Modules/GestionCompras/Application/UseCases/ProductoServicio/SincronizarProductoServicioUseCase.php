<?php

namespace App\Modules\GestionCompras\Application\UseCases\ProductoServicio;

use App\Modules\GestionCompras\Infrastructure\Repositories\CpProductoServicioRepository;
use App\Modules\Gateway\Application\UseCases\BuscarArticulosGatewayUseCase;
use Illuminate\Support\Str;
use Exception;

use App\Exceptions\InvalidPrefixException;

class SincronizarProductoServicioUseCase
{
    protected const PREFIJOS_PERMITIDOS = ['ACT','IMC-','ALM'];

    public function __construct(
        protected CpProductoServicioRepository $repository,
        protected BuscarArticulosGatewayUseCase $buscarGatewayUseCase
    ) {}

    public function execute(string $codigo)
    {
        // 1. Validar prefijo permitido
        if (!Str::startsWith($codigo, self::PREFIJOS_PERMITIDOS)) {
            $prefijos = implode(', ', self::PREFIJOS_PERMITIDOS);
            throw new InvalidPrefixException(
                "El código '{$codigo}' no cumple con los prefijos permitidos: {$prefijos}.",
                self::PREFIJOS_PERMITIDOS
            );
        }

        // 2. Buscamos el artículo en el Gateway por su código
        $articulos = $this->buscarGatewayUseCase->execute($codigo);

        // 3. Filtramos para asegurarnos de que el código coincida exactamente
        $articuloExterno = $articulos->firstWhere('codigo', $codigo);

        if (!$articuloExterno) {
            throw new Exception("El producto/servicio con código {$codigo} no fue encontrado en el sistema externo.");
        }

        // 4. Sincronizamos en la BD local (si existe, actualiza el nombre; si no, lo crea)
        $productoServicio = $this->repository->updateOrCreateByCodigoProducto($codigo, [
            'nombre' => $articuloExterno->nombre
        ]);

        return $productoServicio;
    }
}
