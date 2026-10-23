<?php

namespace App\Modules\Auditoria\Domain\Entities;

class ActividadEntity
{
    public function __construct(
        public readonly int $id,
        public readonly int $usuarioId,
        public readonly string $accion,
        public readonly ?string $tablaAfectada,
        public readonly ?int $registroId,
        public readonly ?string $fecha,
        public readonly ?array $usuario = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            usuarioId: (int) $data['usuario_id'],
            accion: (string) $data['accion'],
            tablaAfectada: $data['tabla_afectada'] ?? null,
            registroId: isset($data['registro_id']) ? (int) $data['registro_id'] : null,
            fecha: isset($data['fecha']) ? (string) $data['fecha'] : null,
            usuario: $data['usuario'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuarioId,
            'accion' => $this->accion,
            'tabla_afectada' => $this->tablaAfectada,
            'registro_id' => $this->registroId,
            'fecha' => $this->fecha,
            'usuario' => $this->usuario,
        ];
    }
}
