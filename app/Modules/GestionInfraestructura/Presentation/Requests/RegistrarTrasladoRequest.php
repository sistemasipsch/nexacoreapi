<?php

namespace App\Modules\GestionInfraestructura\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarTrasladoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_traslado' => 'nullable|date',
            'sede_id' => 'required|integer|exists:sedes,id',
            'area_id' => 'nullable|integer|exists:areas,id',
            'traslado_area_id' => 'nullable|integer|exists:areas,id',
            'traslado_dependencia_id' => 'nullable|integer',
            'responsable_id' => 'nullable|integer|exists:personal,id',
            'custodio_id' => 'nullable|integer|exists:personal,id',
            'ubicacion_especifica' => 'nullable|string|max:255',
            'motivo_observacion' => 'nullable|string|max:500',
            'motivo_traslado' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'sede_id.required' => 'La sede de destino es obligatoria.',
            'sede_id.exists' => 'La sede seleccionada no existe.',
        ];
    }
}
