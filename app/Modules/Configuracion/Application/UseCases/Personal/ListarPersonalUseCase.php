<?php
namespace App\Modules\Configuracion\Application\UseCases\Personal;
use App\Models\Personal;

class ListarPersonalUseCase
{
    public function execute()
    {
        return Personal::with('cargo')->get();
    }
}