<?php

namespace App\Modules\Auditoria\Domain\Entities;

class PedidoProgramadoAuditoriaEntity
{
    public function __construct(
        public readonly int $id,
        public readonly mixed $datosPedido,
        public readonly ?string $fechaProgramada,
        public readonly ?string $firmaProgramador,
        public readonly int $creadoPor,
        public readonly string $estado,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        public readonly ?array $creador = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            datosPedido: is_string($data['datos_pedido'] ?? null) 
                ? json_decode($data['datos_pedido'], true) 
                : ($data['datos_pedido'] ?? null),
            fechaProgramada: isset($data['fecha_programada']) ? (string) $data['fecha_programada'] : null,
            firmaProgramador: $data['firma_programador'] ?? null,
            creadoPor: (int) $data['creado_por'],
            estado: (string) ($data['estado'] ?? 'programado'),
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            creador: $data['creador'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'datos_pedido' => $this->datosPedido,
            'fecha_programada' => $this->fechaProgramada,
            'firma_programador' => $this->firmaProgramador,
            'creado_por' => $this->creadoPor,
            'estado' => $this->estado,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'creador' => $this->creador,
        ];
    }
}
