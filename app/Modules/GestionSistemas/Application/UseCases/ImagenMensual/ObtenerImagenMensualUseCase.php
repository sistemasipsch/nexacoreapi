<?php

namespace App\Modules\GestionSistemas\Application\UseCases\ImagenMensual;

use App\Modules\GestionSistemas\Domain\Entities\ImagenMensualEntity;
use App\Modules\GestionSistemas\Domain\Repositories\ImagenMensualRepositoryInterface;

class ObtenerImagenMensualUseCase
{
    private ImagenMensualRepositoryInterface $repository;

    public function __construct(ImagenMensualRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(): ?ImagenMensualEntity
    {
        return $this->repository->getActive();
    }
}
