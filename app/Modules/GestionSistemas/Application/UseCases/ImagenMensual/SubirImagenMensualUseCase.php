<?php

namespace App\Modules\GestionSistemas\Application\UseCases\ImagenMensual;

use App\Modules\GestionSistemas\Application\DTOs\ImagenMensual\SubirImagenMensualDTO;
use App\Modules\GestionSistemas\Domain\Entities\ImagenMensualEntity;
use App\Modules\GestionSistemas\Domain\Repositories\ImagenMensualRepositoryInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SubirImagenMensualUseCase
{
    private ImagenMensualRepositoryInterface $repository;

    public function __construct(ImagenMensualRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(SubirImagenMensualDTO $dto): ImagenMensualEntity
    {
        // Verificar si existe una imagen anterior
        $activeImage = $this->repository->getActive();

        if ($activeImage) {
            // Eliminar archivo físico
            if (Storage::disk('public')->exists($activeImage->ruta)) {
                Storage::disk('public')->delete($activeImage->ruta);
            }
        }

        // Generar nombre de archivo único
        $archivo = $dto->archivo;
        $extension = $archivo->getClientOriginalExtension();
        $nombreArchivo = Str::random(10) . '.' . $extension;
        $ruta = 'imagenMensual/' . $nombreArchivo;

        // Guardar la nueva imagen
        Storage::disk('public')->putFileAs('imagenMensual', $archivo, $nombreArchivo);

        // Crear la entidad
        $entity = new ImagenMensualEntity(
            id: $activeImage?->id, // Reutilizar el ID si existe, para que sea un update
            nombreOriginal: $archivo->getClientOriginalName(),
            nombreArchivo: $nombreArchivo,
            ruta: $ruta,
            subidoPor: $dto->subidoPor
        );

        // Actualizar (o crear) el registro en la BD
        return $this->repository->save($entity);
    }
}
