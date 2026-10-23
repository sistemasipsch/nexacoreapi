<?php

namespace App\Modules\Auditoria\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListarActividadesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_inicio' => 'nullable|string',
            'fechaInicio' => 'nullable|string',
            'fecha_fin' => 'nullable|string',
            'fechaFin' => 'nullable|string',
            'usuario_id' => 'nullable|integer',
            'usuarioId' => 'nullable|integer',
            'tabla_afectada' => 'nullable|string|max:50',
            'tablaAfectada' => 'nullable|string|max:50',
            'buscar' => 'nullable|string|max:100',
            'q' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'pagina' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'limite' => 'nullable|integer|min:1|max:100',
        ];
    }
}
