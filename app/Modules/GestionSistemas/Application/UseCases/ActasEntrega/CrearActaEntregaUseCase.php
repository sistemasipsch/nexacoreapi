<?php

namespace App\Modules\GestionSistemas\Application\UseCases\ActasEntrega;

use App\Modules\GestionSistemas\Application\DTOs\CrearActaEntregaDTO;
use App\Modules\GestionSistemas\Domain\Contracts\ActaEntregaRepositoryInterface;
use App\Modules\GestionSistemas\Domain\Entities\ActaEntrega;
use App\Modules\GestionSistemas\Domain\Entities\PerifericoEntregado;
use Illuminate\Support\Facades\Storage;
use Exception;

class CrearActaEntregaUseCase
{
    private ActaEntregaRepositoryInterface $repository;

    public function __construct(ActaEntregaRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(CrearActaEntregaDTO $dto): ActaEntrega
    {
        $firmaEntregaPath = null;
        $firmaRecibePath = null;

        if ($dto->firmaGuardadaEntregaPath) {
            $firmaEntregaPath = $dto->firmaGuardadaEntregaPath;
        } elseif ($dto->firmaEntrega) {
            $firmaEntregaPath = $dto->firmaEntrega->store('ActasEntregaEquipos', 'public');
            if (!$firmaEntregaPath) {
                throw new Exception('Error al guardar la firma de entrega.');
            }
        }

        if ($dto->firmaGuardadaRecibePath) {
            $firmaRecibePath = $dto->firmaGuardadaRecibePath;
        } elseif ($dto->firmaRecibe) {
            $firmaRecibePath = $dto->firmaRecibe->store('ActasEntregaEquipos', 'public');
            if (!$firmaRecibePath) {
                throw new Exception('Error al guardar la firma de quien recibe.');
            }
        }

        $perifericos = [];
        foreach ($dto->perifericos as $perifericoDTO) {
            $perifericos[] = new PerifericoEntregado(
                $perifericoDTO->inventarioId,
                $perifericoDTO->cantidad,
                $perifericoDTO->observaciones
            );
        }

        $actaEntrega = new ActaEntrega(
            $dto->equipoId,
            $dto->funcionarioId,
            $dto->fechaEntrega,
            $firmaEntregaPath,
            $firmaRecibePath,
            'entregado', // estado
            null, // devuelto
            $perifericos
        );

        return $this->repository->save($actaEntrega);
    }
}
