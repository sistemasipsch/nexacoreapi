<?php

namespace App\Modules\GestionInfraestructura\Application\UseCases\Equipos;

use App\Models\InfraEquipo;
use Illuminate\Support\Facades\Storage;

class EliminarInfraEquipoUseCase
{
    public function execute(int $id): bool
    {
        $equipo = InfraEquipo::findOrFail($id);

        if (!empty($equipo->imagen_url) && Storage::disk('public')->exists($equipo->imagen_url)) {
            Storage::disk('public')->delete($equipo->imagen_url);
        }

        return (bool) $equipo->delete();
    }
}
