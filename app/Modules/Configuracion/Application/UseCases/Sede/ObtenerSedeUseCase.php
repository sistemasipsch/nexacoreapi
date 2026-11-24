<?php
namespace App\Modules\Configuracion\Application\UseCases\Sede;
use App\Models\Sede;

class ObtenerSedeUseCase
{
    public function execute($id)
    {
        return Sede::with('responsable:id,nombre_completo')->find($id);
    }
}
