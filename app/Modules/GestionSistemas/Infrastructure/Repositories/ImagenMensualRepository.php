<?php

namespace App\Modules\GestionSistemas\Infrastructure\Repositories;

use App\Models\ImagenMensual;
use App\Modules\GestionSistemas\Domain\Entities\ImagenMensualEntity;
use App\Modules\GestionSistemas\Domain\Repositories\ImagenMensualRepositoryInterface;

class ImagenMensualRepository implements ImagenMensualRepositoryInterface
{
    public function getActive(): ?ImagenMensualEntity
    {
        $model = ImagenMensual::first();

        if (!$model) {
            return null;
        }

        return new ImagenMensualEntity(
            id: $model->id,
            nombreOriginal: $model->nombre_original,
            nombreArchivo: $model->nombre_archivo,
            ruta: $model->ruta,
            subidoPor: $model->subido_por,
            createdAt: $model->created_at?->toIso8601String(),
            updatedAt: $model->updated_at?->toIso8601String()
        );
    }

    public function save(ImagenMensualEntity $entity): ImagenMensualEntity
    {
        $model = ImagenMensual::updateOrCreate(
            ['id' => $entity->id],
            [
                'nombre_original' => $entity->nombreOriginal,
                'nombre_archivo' => $entity->nombreArchivo,
                'ruta' => $entity->ruta,
                'subido_por' => $entity->subidoPor,
            ]
        );

        return new ImagenMensualEntity(
            id: $model->id,
            nombreOriginal: $model->nombre_original,
            nombreArchivo: $model->nombre_archivo,
            ruta: $model->ruta,
            subidoPor: $model->subido_por,
            createdAt: $model->created_at?->toIso8601String(),
            updatedAt: $model->updated_at?->toIso8601String()
        );
    }

    public function delete(int $id): void
    {
        ImagenMensual::where('id', $id)->delete();
    }
}
