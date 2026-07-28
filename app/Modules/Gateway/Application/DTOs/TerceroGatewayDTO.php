<?php

namespace App\Modules\Gateway\Application\DTOs;

class TerceroGatewayDTO
{
    public function __construct(
        public readonly string $nit,
        public readonly string $nombre,
        public readonly ?string $genero = null,
        public readonly ?string $fechaNacimiento = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            nit: $data['nit'] ?? '',
            nombre: $data['nombre'] ?? '',
            genero: $data['genero'] ?? null,
            fechaNacimiento: $data['fechaNacimiento'] ?? null
        );
    }
}
