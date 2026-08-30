<?php

namespace App\Modules\GestionSistemas\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarPcEquipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled by PermissionService in the controller
    }

    public function rules(): array
    {
        // Get the ID from the route parameter
        $id = $this->route('id');

        return [
            'serial' => 'sometimes|string|max:255|unique:pc_equipos,serial,' . $id,
            'numero_inventario' => 'nullable|string|max:255|unique:pc_equipos,numero_inventario,' . $id,
            'nombre_equipo' => 'nullable|string|max:255',
            'marca' => 'nullable|string|max:255',
            'modelo' => 'nullable|string|max:255',
            'tipo' => 'nullable|string|max:255',
            'propiedad' => 'nullable|in:empleado,empresa',
            'ip_fija' => 'sometimes|nullable|ipv4',
            'sede_id' => 'nullable|integer|exists:sedes,id',
            'area_id' => 'nullable|integer|exists:areas,id',
            'responsable_id' => 'nullable|integer|exists:personal,id',
            'estado' => 'nullable|string|max:255',
            'fecha_ingreso' => 'nullable|date',
            'imagen' => 'nullable|image|max:51200',
            'eliminar_imagen' => 'nullable|boolean',
            'fecha_entrega' => 'nullable|date',
            'descripcion_general' => 'nullable|string',
            'garantia_meses' => 'nullable|integer',
            'forma_adquisicion' => 'nullable|in:compra,alquiler,donacion,comodato',
            'observaciones' => 'nullable|string',
            'repuestos_principales' => 'nullable|string',
            'recomendaciones' => 'nullable|string',
            'equipos_adicionales' => 'nullable|string',
        ];
    }
}
