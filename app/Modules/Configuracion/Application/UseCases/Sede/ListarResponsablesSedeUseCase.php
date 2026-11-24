<?php
namespace App\Modules\Configuracion\Application\UseCases\Sede;

use App\Models\Usuario;

/** Usuarios activos que se pueden asignar como responsables de una sede */
class ListarResponsablesSedeUseCase
{
    public function execute()
    {
        return Usuario::with('rol:id,nombre', 'sede:id,nombre')
            ->where('estado', 1)
            ->orderBy('nombre_completo')
            ->get(['id', 'nombre_completo', 'rol_id', 'sede_id'])
            ->map(fn (Usuario $u) => [
                'id' => $u->id,
                'nombre_completo' => $u->nombre_completo,
                'rol' => $u->rol?->nombre,
                'sede' => $u->sede?->nombre,
            ])
            ->values();
    }
}
