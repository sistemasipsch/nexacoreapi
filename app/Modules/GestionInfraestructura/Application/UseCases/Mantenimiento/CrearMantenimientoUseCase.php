<?php
namespace App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento;

use App\Models\Mantenimiento;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class CrearMantenimientoUseCase
{
    public function execute(array $data, $request = null)
    {
        $user = Auth::guard('api')->user();
        $data['creado_por'] = $user ? $user->id : null;
        $data['fecha_creacion'] = Carbon::now();
        $data['fecha_ultima_actualizacion'] = Carbon::now();

        if ($request) {
            $paths = [];
            foreach (['imagen', 'imagen2'] as $field) {
                if ($request->hasFile($field)) {
                    $file = $request->file($field);
                    $filename = md5($file->getClientOriginalName() . time() . uniqid()) . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('mantenimientos', $filename, 'public');
                    $paths[] = 'storage/' . $path;
                }
            }
            if (!empty($paths)) {
                $data['imagen'] = implode(',', $paths);
            } else {
                throw new \Exception('Se requiere al menos una imagen (evidencia) para crear el mantenimiento.', 422);
            }
        } else {
            throw new \Exception('Se requiere al menos una imagen (evidencia) para crear el mantenimiento.', 422);
        }

        return Mantenimiento::create($data);
    }
}