<?php

namespace App\Modules\GestionSistemas\Application\DTOs;

use Illuminate\Http\UploadedFile;

class CrearActaDevolucionDTO
{
    public int $entregaId;
    public string $fechaDevolucion;
    public ?string $observaciones;
    public ?UploadedFile $firmaEntregaFile;
    public ?UploadedFile $firmaRecibeFile;
    public ?string $firmaGuardadaEntregaPath;
    public ?string $firmaGuardadaRecibePath;

    public function __construct(
        int $entregaId,
        string $fechaDevolucion,
        ?string $observaciones = null,
        ?UploadedFile $firmaEntregaFile = null,
        ?UploadedFile $firmaRecibeFile = null,
        ?string $firmaGuardadaEntregaPath = null,
        ?string $firmaGuardadaRecibePath = null
    ) {
        $this->entregaId = $entregaId;
        $this->fechaDevolucion = $fechaDevolucion;
        $this->observaciones = $observaciones;
        $this->firmaEntregaFile = $firmaEntregaFile;
        $this->firmaRecibeFile = $firmaRecibeFile;
        $this->firmaGuardadaEntregaPath = $firmaGuardadaEntregaPath;
        $this->firmaGuardadaRecibePath = $firmaGuardadaRecibePath;
    }
}
