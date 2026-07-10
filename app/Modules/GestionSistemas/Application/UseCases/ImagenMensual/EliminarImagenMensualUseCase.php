<?php

namespace App\Modules\GestionSistemas\Application\UseCases\ImagenMensual;

use App\Modules\GestionSistemas\Domain\Repositories\ImagenMensualRepositoryInterface;
use Illuminate\Support\Facades\Storage;

class EliminarImagenMensualUseCase
{
    private ImagenMensualRepositoryInterface $repository;

    public function __construct(ImagenMensualRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(): void
    {
        $activeImage = $this->repository->getActive();

        if ($activeImage) {
            if (Storage::disk('public')->exists($activeImage->ruta)) {
                Storage::disk('public')->delete($activeImage->ruta);
            }
            $this->repository->delete($activeImage->id);
        }
    }
}
