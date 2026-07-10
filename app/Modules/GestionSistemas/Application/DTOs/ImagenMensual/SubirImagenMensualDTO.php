<?php

namespace App\Modules\GestionSistemas\Application\DTOs\ImagenMensual;

use Illuminate\Http\UploadedFile;

class SubirImagenMensualDTO
{
    public function __construct(
        public readonly UploadedFile $archivo,
        public readonly int $subidoPor
    ) {
    }
}
