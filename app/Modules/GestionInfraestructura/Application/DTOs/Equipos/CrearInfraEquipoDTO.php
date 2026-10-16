<?php

namespace App\Modules\GestionInfraestructura\Application\DTOs\Equipos;

use Illuminate\Http\Request;

class CrearInfraEquipoDTO
{
    public function __construct(
        public readonly string $nombre_equipo,
        public readonly string $tipo_equipo,
        public readonly int $sede_id,
        public readonly string $forma_adquisicion,
        public readonly ?string $marca = null,
        public readonly ?string $modelo = null,
        public readonly ?string $serial = null,
        public readonly ?string $activo_fijo = null,
        public readonly ?string $capacidad = null,
        public readonly ?string $voltaje = null,
        public readonly ?string $amperaje = null,
        public readonly ?string $frecuencia = '60 Hz',
        public readonly ?int $area_id = null,
        public readonly ?string $ubicacion_especifica = null,
        public readonly ?int $responsable_id = null,
        public readonly ?int $proveedor_id = null,
        public readonly ?string $fecha_compra = null,
        public readonly ?int $garantia_meses = null,
        public readonly ?string $fecha_instalacion = null,
        public readonly ?int $vida_util_anos = null,
        public readonly ?string $frecuencia_mantenimiento = 'Trimestral',
        public readonly string $estado_equipo = 'nuevo',
        public readonly ?string $descripcion_general = null,
        public readonly ?string $observaciones = null,
        public readonly ?string $repuestos_principales = null,
        public readonly ?string $recomendaciones = null,
        public readonly ?string $equipos_adicionales = null,
        public readonly ?string $imagen_url = null,
    ) {}

    public static function fromRequest(Request $request, ?string $imagenUrl = null): self
    {
        return new self(
            nombre_equipo: (string) $request->input('nombre_equipo'),
            tipo_equipo: (string) $request->input('tipo_equipo'),
            sede_id: (int) $request->input('sede_id'),
            forma_adquisicion: (string) ($request->input('forma_adquisicion') ?? 'compra directa'),
            marca: $request->input('marca'),
            modelo: $request->input('modelo'),
            serial: $request->input('serial'),
            activo_fijo: $request->input('activo_fijo'),
            capacidad: $request->input('capacidad'),
            voltaje: $request->input('voltaje'),
            amperaje: $request->input('amperaje'),
            frecuencia: $request->input('frecuencia') ?? '60 Hz',
            area_id: $request->filled('area_id') ? (int) $request->input('area_id') : null,
            ubicacion_especifica: $request->input('ubicacion_especifica'),
            responsable_id: $request->filled('responsable_id') ? (int) $request->input('responsable_id') : null,
            proveedor_id: $request->filled('proveedor_id') ? (int) $request->input('proveedor_id') : null,
            fecha_compra: $request->input('fecha_compra'),
            garantia_meses: $request->filled('garantia_meses') ? (int) $request->input('garantia_meses') : null,
            fecha_instalacion: $request->input('fecha_instalacion'),
            vida_util_anos: $request->filled('vida_util_anos') ? (int) $request->input('vida_util_anos') : null,
            frecuencia_mantenimiento: $request->input('frecuencia_mantenimiento') ?? 'Trimestral',
            estado_equipo: $request->input('estado_equipo') ?? 'nuevo',
            descripcion_general: $request->input('descripcion_general'),
            observaciones: $request->input('observaciones'),
            repuestos_principales: $request->input('repuestos_principales'),
            recomendaciones: $request->input('recomendaciones'),
            equipos_adicionales: $request->input('equipos_adicionales'),
            imagen_url: $imagenUrl,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'nombre_equipo' => $this->nombre_equipo,
            'tipo_equipo' => $this->tipo_equipo,
            'sede_id' => $this->sede_id,
            'forma_adquisicion' => $this->forma_adquisicion,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'serial' => $this->serial,
            'activo_fijo' => $this->activo_fijo,
            'capacidad' => $this->capacidad,
            'voltaje' => $this->voltaje,
            'amperaje' => $this->amperaje,
            'frecuencia' => $this->frecuencia,
            'area_id' => $this->area_id,
            'ubicacion_especifica' => $this->ubicacion_especifica,
            'responsable_id' => $this->responsable_id,
            'proveedor_id' => $this->proveedor_id,
            'fecha_compra' => $this->fecha_compra,
            'garantia_meses' => $this->garantia_meses,
            'fecha_instalacion' => $this->fecha_instalacion,
            'vida_util_anos' => $this->vida_util_anos,
            'frecuencia_mantenimiento' => $this->frecuencia_mantenimiento,
            'estado_equipo' => $this->estado_equipo,
            'descripcion_general' => $this->descripcion_general,
            'observaciones' => $this->observaciones,
            'repuestos_principales' => $this->repuestos_principales,
            'recomendaciones' => $this->recomendaciones,
            'equipos_adicionales' => $this->equipos_adicionales,
            'imagen_url' => $this->imagen_url,
        ], fn($v) => !is_null($v));
    }
}
