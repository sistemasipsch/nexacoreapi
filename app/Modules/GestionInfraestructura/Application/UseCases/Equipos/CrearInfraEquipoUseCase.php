<?php

namespace App\Modules\GestionInfraestructura\Application\UseCases\Equipos;

use App\Models\InfraEquipo;
use App\Modules\GestionInfraestructura\Application\DTOs\Equipos\CrearInfraEquipoDTO;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CrearInfraEquipoUseCase
{
    public function execute(CrearInfraEquipoDTO $dto, ?UploadedFile $imagen = null, ?int $userId = null): InfraEquipo
    {
        $data = $dto->toArray();

        if ($imagen && $imagen->isValid()) {
            $nombreArchivo = 'infra_' . time() . '_' . Str::random(8) . '.' . $imagen->getClientOriginalExtension();
            $path = $imagen->storeAs('infraEquipos', $nombreArchivo, 'public');
            $data['imagen_url'] = $path;
        }

        if (empty($data['activo_fijo'])) {
            $siguiente = ((int) InfraEquipo::max('id')) + 1;
            $data['activo_fijo'] = 'INF-AIR-' . str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
        }

        $data['creado_por'] = $userId ?? auth()->id();

        $equipo = InfraEquipo::create($data);

        return $equipo->load([
            'sede',
            'area',
            'responsable',
            'proveedor',
            'creador',
        ]);
    }
}
