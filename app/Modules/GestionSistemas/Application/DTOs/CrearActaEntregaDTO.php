<?php

namespace App\Modules\GestionSistemas\Application\DTOs;

use Illuminate\Http\UploadedFile;

class CrearActaEntregaDTO
{
    public int $equipoId;
    public int $funcionarioId;
    public string $fechaEntrega;
    /** @var UploadedFile|string|null */
    public $firmaEntrega;
    /** @var UploadedFile|string|null */
    public $firmaRecibe;
    public ?string $firmaGuardadaEntregaPath;
    public ?string $firmaGuardadaRecibePath;
    /** @var PerifericoDTO[] */
    public array $perifericos;

    public function __construct(
        int $equipoId,
        int $funcionarioId,
        string $fechaEntrega,
        $firmaEntrega = null,
        $firmaRecibe = null,
        ?string $firmaGuardadaEntregaPath = null,
        ?string $firmaGuardadaRecibePath = null,
        array $perifericos = []
    ) {
        $this->equipoId = $equipoId;
        $this->funcionarioId = $funcionarioId;
        $this->fechaEntrega = $fechaEntrega;
        $this->firmaEntrega = $firmaEntrega;
        $this->firmaRecibe = $firmaRecibe;
        $this->firmaGuardadaEntregaPath = $firmaGuardadaEntregaPath;
        $this->firmaGuardadaRecibePath = $firmaGuardadaRecibePath;
        $this->perifericos = $perifericos;
    }
}
