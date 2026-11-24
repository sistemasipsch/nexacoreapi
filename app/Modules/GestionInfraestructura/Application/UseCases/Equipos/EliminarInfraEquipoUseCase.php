<?php

namespace App\Modules\GestionInfraestructura\Application\UseCases\Equipos;

use App\Models\InfraEquipo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EliminarInfraEquipoUseCase
{
    public function execute(int $id): bool
    {
        $equipo = InfraEquipo::findOrFail($id);

        // No se permite eliminar equipos con mantenimientos registrados (vinculados por id,
        // activo fijo o serial), para no perder la trazabilidad de su hoja de vida.
        $totalMantenimientos = (new InfraEquipoMantenimientoResolver())->mantenimientosDeEquipo($equipo)->count();
        if ($totalMantenimientos > 0) {
            throw new HttpException(422, sprintf(
                'No se puede eliminar el equipo "%s"%s porque tiene %d mantenimiento%s registrado%s.',
                $equipo->nombre_equipo ?: 'de infraestructura',
                $equipo->activo_fijo ? " ({$equipo->activo_fijo})" : '',
                $totalMantenimientos,
                $totalMantenimientos === 1 ? '' : 's',
                $totalMantenimientos === 1 ? '' : 's'
            ));
        }

        if (!empty($equipo->imagen_url) && Storage::disk('public')->exists($equipo->imagen_url)) {
            Storage::disk('public')->delete($equipo->imagen_url);
        }

        return (bool) $equipo->delete();
    }
}
