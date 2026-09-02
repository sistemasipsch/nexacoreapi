<?php
namespace App\Modules\GestionSistemas\Application\DTOs;

use Illuminate\Http\UploadedFile;

class ActualizarActaDevolucionDTO
{
    public int $id;
    public ?int $entregaId;
    public ?string $fechaDevolucion;
    public ?string $observaciones;
    public ?UploadedFile $firmaEntregaFile;
    public ?UploadedFile $firmaRecibeFile;

    public function __construct(
        int $id,
        ?int $entregaId = null,
        ?string $fechaDevolucion = null,
        ?string $observaciones = null,
        ?UploadedFile $firmaEntregaFile = null,
        ?UploadedFile $firmaRecibeFile = null
    ) {
        $this->id = $id;
        $this->entregaId = $entregaId;
        $this->fechaDevolucion = $fechaDevolucion;
        $this->observaciones = $observaciones;
        $this->firmaEntregaFile = $firmaEntregaFile;
        $this->firmaRecibeFile = $firmaRecibeFile;
    }
}
