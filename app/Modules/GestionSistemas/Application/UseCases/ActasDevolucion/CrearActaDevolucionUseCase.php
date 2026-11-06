<?php

namespace App\Modules\GestionSistemas\Application\UseCases\ActasDevolucion;

use App\Modules\GestionSistemas\Application\DTOs\CrearActaDevolucionDTO;
use App\Modules\GestionSistemas\Domain\Entities\ActaDevolucion;
use App\Modules\GestionSistemas\Infrastructure\Repositories\ActaDevolucionRepository;

class CrearActaDevolucionUseCase
{
    private ActaDevolucionRepository $repository;

    public function __construct(ActaDevolucionRepository $repository)
    {
        $this->repository = $repository;
    }

    public function execute(CrearActaDevolucionDTO $dto): ActaDevolucion
    {
        $acta = new ActaDevolucion(
            $dto->entregaId,
            $dto->fechaDevolucion,
            $dto->observaciones
        );

        if ($dto->firmaGuardadaEntregaPath) {
            $acta->setFirmaEntrega($dto->firmaGuardadaEntregaPath);
        } elseif ($dto->firmaEntregaFile) {
            $path = $dto->firmaEntregaFile->store('firmas_devolucion', 'public');
            $acta->setFirmaEntrega($path);
        }

        if ($dto->firmaGuardadaRecibePath) {
            $acta->setFirmaRecibe($dto->firmaGuardadaRecibePath);
        } elseif ($dto->firmaRecibeFile) {
            $path = $dto->firmaRecibeFile->store('firmas_devolucion', 'public');
            $acta->setFirmaRecibe($path);
        }

        return $this->repository->save($acta);
    }
}
