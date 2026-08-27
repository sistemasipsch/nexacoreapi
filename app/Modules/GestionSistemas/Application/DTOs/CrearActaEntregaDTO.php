<?php

namespace App\Modules\GestionSistemas\Application\DTOs;

use Illuminate\Http\UploadedFile;

class CrearActaEntregaDTO
{
    public int $equipoId;
    public int $funcionarioId;
    public string $fechaEntrega;
    /** @var UploadedFile|null */
    public ?UploadedFile $firmaEntrega;
    /** @var UploadedFile|null */
    public ?UploadedFile $firmaRecibe;
    /** @var string|null */
    public ?string $firmaGuardadaEntregaPath;
    /** @var string|null */
    public ?string $firmaGuardadaRecibePath;
    /** @var PerifericoDTO[] */
    public array $perifericos;

    public function __construct(
        int $equipoId,
        int $funcionarioId,
        string $fechaEntrega,
        ?UploadedFile $firmaEntrega = null,
        ?UploadedFile $firmaRecibe = null,
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
