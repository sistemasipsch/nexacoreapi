<?php

namespace App\Modules\GestionSistemas\Application\UseCases\ActasDevolucion;

use App\Modules\GestionSistemas\Application\DTOs\ActualizarActaDevolucionDTO;
use App\Modules\GestionSistemas\Domain\Entities\ActaDevolucion;
use App\Modules\GestionSistemas\Infrastructure\Repositories\ActaDevolucionRepository;
use Exception;

class ActualizarActaDevolucionUseCase
{
    private ActaDevolucionRepository $repository;

    public function __construct(ActaDevolucionRepository $repository)
    {
        $this->repository = $repository;
    }

    public function execute(ActualizarActaDevolucionDTO $dto): ActaDevolucion
    {
        $existente = $this->repository->findById($dto->id);
        if (!$existente) {
            throw new Exception("Acta de devolución no encontrada");
        }

        $entregaId = $dto->entregaId ?? $existente->getEntregaId();
        $fechaDevolucion = $dto->fechaDevolucion ?? $existente->getFechaDevolucion();
        $observaciones = $dto->observaciones !== null ? $dto->observaciones : $existente->getObservaciones();
        
        $firmaEntrega = $existente->getFirmaEntrega();
        if ($dto->firmaEntregaFile) {
            $firmaEntrega = $dto->firmaEntregaFile->store('firmas_devolucion', 'public');
        }

        $firmaRecibe = $existente->getFirmaRecibe();
        if ($dto->firmaRecibeFile) {
            $firmaRecibe = $dto->firmaRecibeFile->store('firmas_devolucion', 'public');
        }

        $acta = new ActaDevolucion(
            $entregaId,
            $fechaDevolucion,
            $observaciones,
            $firmaEntrega,
            $firmaRecibe,
            $dto->id
        );

        $actualizado = $this->repository->update($acta);
        if (!$actualizado) {
            throw new Exception("No se pudo actualizar el acta de devolución");
        }

        return $actualizado;
    }
}
