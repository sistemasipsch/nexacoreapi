<?php

namespace App\Modules\GestionSistemas\Domain\Entities;

class ImagenMensualEntity
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $nombreOriginal,
        public readonly string $nombreArchivo,
        public readonly string $ruta,
        public readonly int $subidoPor,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nombre_original' => $this->nombreOriginal,
            'nombre_archivo' => $this->nombreArchivo,
            'ruta' => $this->ruta,
            'subido_por' => $this->subidoPor,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
