<?php

namespace App\Modules\Gateway\Application\DTOs;

class ArticuloGatewayDTO
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly ?string $descripcion = null,
        public readonly ?string $tipo = null // 'producto' o 'servicio'
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            codigo: $data['codigoProd'] ?? $data['codigo'] ?? '',
            nombre: $data['nombreProd'] ?? $data['nombre'] ?? '',
            descripcion: $data['descripcion'] ?? null,
            tipo: $data['tipo'] ?? null
        );
    }
}
