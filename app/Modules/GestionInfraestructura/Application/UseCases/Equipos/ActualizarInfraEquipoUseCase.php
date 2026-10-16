<?php

namespace App\Modules\GestionInfraestructura\Application\UseCases\Equipos;

use App\Models\InfraEquipo;
use App\Modules\GestionInfraestructura\Application\DTOs\Equipos\ActualizarInfraEquipoDTO;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ActualizarInfraEquipoUseCase
{
    public function execute(int $id, ActualizarInfraEquipoDTO $dto, ?UploadedFile $nuevaImagen = null): InfraEquipo
    {
        $equipo = InfraEquipo::findOrFail($id);
        $data = $dto->toArray();

        if ($dto->eliminar_imagen) {
            if (!empty($equipo->imagen_url) && Storage::disk('public')->exists($equipo->imagen_url)) {
                Storage::disk('public')->delete($equipo->imagen_url);
            }
            $data['imagen_url'] = null;
        }

        if ($nuevaImagen && $nuevaImagen->isValid()) {
            // Eliminar imagen anterior si existe
            if (!empty($equipo->imagen_url) && Storage::disk('public')->exists($equipo->imagen_url)) {
                Storage::disk('public')->delete($equipo->imagen_url);
            }

            $nombreArchivo = 'infra_' . time() . '_' . Str::random(8) . '.' . $nuevaImagen->getClientOriginalExtension();
            $path = $nuevaImagen->storeAs('infraEquipos', $nombreArchivo, 'public');
            $data['imagen_url'] = $path;
        }

        $equipo->update($data);

        return $equipo->fresh([
            'sede',
            'area',
            'responsable',
            'proveedor',
            'trasladoDependencia',
            'trasladoArea',
            'creador',
        ]);
    }
}
