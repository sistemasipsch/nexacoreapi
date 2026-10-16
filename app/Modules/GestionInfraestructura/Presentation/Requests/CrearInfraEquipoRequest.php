<?php

namespace App\Modules\GestionInfraestructura\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrearInfraEquipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $forma = strtolower(trim((string) $this->input('forma_adquisicion', '')));
        if ($forma === 'compra' || empty($forma)) {
            $forma = 'compra directa';
        }

        $this->merge([
            'forma_adquisicion' => $forma,
            'estado_equipo' => $this->filled('estado_equipo') ? $this->input('estado_equipo') : 'nuevo',
            'frecuencia' => $this->filled('frecuencia') ? $this->input('frecuencia') : '60 Hz',
            'frecuencia_mantenimiento' => $this->filled('frecuencia_mantenimiento') ? $this->input('frecuencia_mantenimiento') : 'Trimestral',
            'serial' => $this->filled('serial') ? trim((string) $this->input('serial')) : null,
            'activo_fijo' => $this->filled('activo_fijo') ? trim((string) $this->input('activo_fijo')) : null,
            'area_id' => $this->filled('area_id') ? (int) $this->input('area_id') : null,
            'responsable_id' => $this->filled('responsable_id') ? (int) $this->input('responsable_id') : null,
            'proveedor_id' => $this->filled('proveedor_id') ? (int) $this->input('proveedor_id') : null,
            'garantia_meses' => $this->filled('garantia_meses') ? (int) $this->input('garantia_meses') : null,
            'vida_util_anos' => $this->filled('vida_util_anos') ? (int) $this->input('vida_util_anos') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre_equipo' => 'required|string|max:255',
            'tipo_equipo' => 'required|string|max:100',
            'sede_id' => 'required|integer|exists:sedes,id',
            'forma_adquisicion' => 'nullable|in:compra directa,compra,alquiler,donacion,comodato',
            'serial' => 'nullable|string|max:150|unique:inventario_infraestructura,serial',
            'activo_fijo' => 'nullable|string|max:100|unique:inventario_infraestructura,activo_fijo',
            'marca' => 'nullable|string|max:100',
            'modelo' => 'nullable|string|max:100',
            'capacidad' => 'nullable|string|max:50',
            'voltaje' => 'nullable|string|max:50',
            'amperaje' => 'nullable|string|max:50',
            'frecuencia' => 'nullable|string|max:50',
            'area_id' => 'nullable|integer|exists:areas,id',
            'ubicacion_especifica' => 'nullable|string|max:255',
            'responsable_id' => 'nullable|integer|exists:personal,id',
            'proveedor_id' => 'nullable|integer|exists:cp_proveedores,id',
            'fecha_compra' => 'nullable|date',
            'garantia_meses' => 'nullable|integer|min:0',
            'fecha_instalacion' => 'nullable|date',
            'vida_util_anos' => 'nullable|integer|min:0',
            'frecuencia_mantenimiento' => 'nullable|string|max:50',
            'estado_equipo' => 'nullable|in:nuevo,usado,bueno,regular,malo,de_baja',
            'descripcion_general' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'repuestos_principales' => 'nullable|string',
            'recomendaciones' => 'nullable|string',
            'equipos_adicionales' => 'nullable|string',
            'imagen' => 'nullable|image|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_equipo.required' => 'El nombre del equipo es obligatorio.',
            'tipo_equipo.required' => 'El tipo de equipo es obligatorio (Ej: Minisplit, Central, etc.).',
            'sede_id.required' => 'Debe seleccionar una sede válida.',
            'sede_id.exists' => 'La sede seleccionada no existe.',
            'serial.unique' => 'Ya existe un equipo registrado con este número de serie.',
            'activo_fijo.unique' => 'Ya existe un equipo con esta placa de activo fijo.',
            'imagen.image' => 'El archivo adjunto debe ser una imagen válida (JPG, PNG, WebP).',
            'imagen.max' => 'La imagen no puede exceder los 10 MB.',
        ];
    }
}
