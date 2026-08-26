<?php
namespace App\Modules\Configuracion\Application\UseCases\Personal;
use App\Models\Personal;

class ObtenerPersonalUseCase
{
    public function execute($id)
    {
        return Personal::with('cargo')->find($id);
    }
}