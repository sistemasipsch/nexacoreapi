<?php

namespace App\Modules\GestionSistemas\Application\DTOs;

use Illuminate\Http\UploadedFile;

class ActualizarActaEntregaDTO
{
    /**
     * @param int $id
     * @param int|null $equipo_id
     * @param int|null $funcionario_id
     * @param string|null $fecha_entrega
     * @param UploadedFile|null $firma_entrega
     * @param UploadedFile|null $firma_recibe
     * @param string|null $firmaGuardadaEntregaPath
     * @param string|null $estado
     * @param string|null $devuelto
     * @param PerifericoDTO[]|null $perifericos
     */
    public function __construct(
        private int $id,
        private ?int $equipo_id = null,
        private ?int $funcionario_id = null,
        private ?string $fecha_entrega = null,
        private $firma_entrega = null,
        private $firma_recibe = null,
        private ?string $firmaGuardadaEntregaPath = null,
        private ?string $firmaGuardadaRecibePath = null,
        private ?string $estado = null,
        private ?string $devuelto = null,
        private ?array $perifericos = null
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEquipoId(): ?int
    {
        return $this->equipo_id;
    }

    public function getFuncionarioId(): ?int
    {
        return $this->funcionario_id;
    }

    public function getFechaEntrega(): ?string
    {
        return $this->fecha_entrega;
    }

    public function getFirmaEntrega()
    {
        return $this->firma_entrega;
    }

    public function getFirmaRecibe()
    {
        return $this->firma_recibe;
    }

    public function getFirmaGuardadaEntregaPath(): ?string
    {
        return $this->firmaGuardadaEntregaPath;
    }

    public function getFirmaGuardadaRecibePath(): ?string
    {
        return $this->firmaGuardadaRecibePath;
    }

    public function getEstado(): ?string
    {
        return $this->estado;
    }

    public function getDevuelto(): ?string
    {
        return $this->devuelto;
    }

    public function getPerifericos(): ?array
    {
        return $this->perifericos;
    }
}
