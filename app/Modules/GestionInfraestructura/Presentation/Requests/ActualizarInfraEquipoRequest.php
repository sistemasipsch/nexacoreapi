<?php

namespace App\Modules\GestionInfraestructura\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarInfraEquipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('forma_adquisicion')) {
            $forma = strtolower(trim((string) $this->input('forma_adquisicion', '')));
            if ($forma === 'compra') {
                $forma = 'compra directa';
            }
            $this->merge(['forma_adquisicion' => $forma ?: 'compra directa']);
        }

        if ($this->has('serial') && empty($this->input('serial'))) {
            $this->merge(['serial' => null]);
        }
        if ($this->has('activo_fijo') && empty($this->input('activo_fijo'))) {
            $this->merge(['activo_fijo' => null]);
        }
        if ($this->has('area_id') && empty($this->input('area_id'))) {
            $this->merge(['area_id' => null]);
        }
        if ($this->has('responsable_id') && empty($this->input('responsable_id'))) {
            $this->merge(['responsable_id' => null]);
        }
        if ($this->has('proveedor_id') && empty($this->input('proveedor_id'))) {
            $this->merge(['proveedor_id' => null]);
        }
        if ($this->has('garantia_meses') && ($this->input('garantia_meses') === '' || $this->input('garantia_meses') === null)) {
            $this->merge(['garantia_meses' => null]);
        }
        if ($this->has('vida_util_anos') && ($this->input('vida_util_anos') === '' || $this->input('vida_util_anos') === null)) {
            $this->merge(['vida_util_anos' => null]);
        }
        if ($this->has('fecha_compra') && empty($this->input('fecha_compra'))) {
            $this->merge(['fecha_compra' => null]);
        }
        if ($this->has('fecha_instalacion') && empty($this->input('fecha_instalacion'))) {
            $this->merge(['fecha_instalacion' => null]);
        }
    }

    public function rules(): array
    {
        $equipoId = $this->route('id') ?? $this->route('equipo');

        return [
            'nombre_equipo' => 'sometimes|required|string|max:255',
            'tipo_equipo' => 'sometimes|required|string|max:100',
            'sede_id' => 'sometimes|required|integer|exists:sedes,id',
            'forma_adquisicion' => 'nullable|in:compra directa,compra,alquiler,donacion,comodato',
            'serial' => "nullable|string|max:150|unique:inventario_infraestructura,serial,{$equipoId},id",
            'activo_fijo' => "nullable|string|max:100|unique:inventario_infraestructura,activo_fijo,{$equipoId},id",
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
            'eliminar_imagen' => 'nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'serial.unique' => 'Ya existe otro equipo registrado con este número de serie.',
            'activo_fijo.unique' => 'Ya existe otro equipo con esta placa de activo fijo.',
            'imagen.image' => 'El archivo adjunto debe ser una imagen válida (JPG, PNG, WebP).',
            'imagen.max' => 'La imagen no puede exceder los 10 MB.',
        ];
    }
}
