<?php

namespace App\Modules\GestionSistemas\Domain\Repositories;

use App\Modules\GestionSistemas\Domain\Entities\ImagenMensualEntity;

interface ImagenMensualRepositoryInterface
{
    public function getActive(): ?ImagenMensualEntity;
    public function save(ImagenMensualEntity $entity): ImagenMensualEntity;
    public function delete(int $id): void;
}
