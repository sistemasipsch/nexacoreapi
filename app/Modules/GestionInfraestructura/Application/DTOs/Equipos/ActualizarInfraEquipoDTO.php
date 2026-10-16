<?php

namespace App\Modules\GestionInfraestructura\Application\DTOs\Equipos;

use Illuminate\Http\Request;

class ActualizarInfraEquipoDTO
{
    public function __construct(
        public readonly ?string $nombre_equipo = null,
        public readonly ?string $tipo_equipo = null,
        public readonly ?int $sede_id = null,
        public readonly ?string $forma_adquisicion = null,
        public readonly ?string $marca = null,
        public readonly ?string $modelo = null,
        public readonly ?string $serial = null,
        public readonly ?string $activo_fijo = null,
        public readonly ?string $capacidad = null,
        public readonly ?string $voltaje = null,
        public readonly ?string $amperaje = null,
        public readonly ?string $frecuencia = null,
        public readonly ?int $area_id = null,
        public readonly ?string $ubicacion_especifica = null,
        public readonly ?int $responsable_id = null,
        public readonly ?int $proveedor_id = null,
        public readonly ?string $fecha_compra = null,
        public readonly ?int $garantia_meses = null,
        public readonly ?string $fecha_instalacion = null,
        public readonly ?int $vida_util_anos = null,
        public readonly ?string $frecuencia_mantenimiento = null,
        public readonly ?string $estado_equipo = null,
        public readonly ?string $descripcion_general = null,
        public readonly ?string $observaciones = null,
        public readonly ?string $repuestos_principales = null,
        public readonly ?string $recomendaciones = null,
        public readonly ?string $equipos_adicionales = null,
        public readonly ?string $imagen_url = null,
        public readonly bool $eliminar_imagen = false,
        public readonly array $camposPresentes = [],
    ) {}

    public static function fromRequest(Request $request, ?string $imagenUrl = null): self
    {
        $campos = [
            'nombre_equipo', 'tipo_equipo', 'sede_id', 'forma_adquisicion', 'marca',
            'modelo', 'serial', 'activo_fijo', 'capacidad', 'voltaje', 'amperaje',
            'frecuencia', 'area_id', 'ubicacion_especifica', 'responsable_id',
            'proveedor_id', 'fecha_compra', 'garantia_meses', 'fecha_instalacion',
            'vida_util_anos', 'frecuencia_mantenimiento', 'estado_equipo',
            'descripcion_general', 'observaciones', 'repuestos_principales',
            'recomendaciones', 'equipos_adicionales'
        ];

        $presentes = [];
        foreach ($campos as $c) {
            if ($request->has($c)) {
                $presentes[] = $c;
            }
        }

        $eliminarImagen = $request->boolean('eliminar_imagen') || $request->input('eliminar_imagen') === '1' || $request->input('eliminar_imagen') === 1;

        return new self(
            nombre_equipo: $request->has('nombre_equipo') ? ($request->filled('nombre_equipo') ? (string) $request->input('nombre_equipo') : null) : null,
            tipo_equipo: $request->has('tipo_equipo') ? ($request->filled('tipo_equipo') ? (string) $request->input('tipo_equipo') : null) : null,
            sede_id: $request->has('sede_id') ? ($request->filled('sede_id') ? (int) $request->input('sede_id') : null) : null,
            forma_adquisicion: $request->has('forma_adquisicion') ? ($request->filled('forma_adquisicion') ? (string) $request->input('forma_adquisicion') : null) : null,
            marca: $request->has('marca') ? ($request->filled('marca') ? (string) $request->input('marca') : null) : null,
            modelo: $request->has('modelo') ? ($request->filled('modelo') ? (string) $request->input('modelo') : null) : null,
            serial: $request->has('serial') ? ($request->filled('serial') ? (string) $request->input('serial') : null) : null,
            activo_fijo: $request->has('activo_fijo') ? ($request->filled('activo_fijo') ? (string) $request->input('activo_fijo') : null) : null,
            capacidad: $request->has('capacidad') ? ($request->filled('capacidad') ? (string) $request->input('capacidad') : null) : null,
            voltaje: $request->has('voltaje') ? ($request->filled('voltaje') ? (string) $request->input('voltaje') : null) : null,
            amperaje: $request->has('amperaje') ? ($request->filled('amperaje') ? (string) $request->input('amperaje') : null) : null,
            frecuencia: $request->has('frecuencia') ? ($request->filled('frecuencia') ? (string) $request->input('frecuencia') : null) : null,
            area_id: $request->has('area_id') ? ($request->filled('area_id') ? (int) $request->input('area_id') : null) : null,
            ubicacion_especifica: $request->has('ubicacion_especifica') ? ($request->filled('ubicacion_especifica') ? (string) $request->input('ubicacion_especifica') : null) : null,
            responsable_id: $request->has('responsable_id') ? ($request->filled('responsable_id') ? (int) $request->input('responsable_id') : null) : null,
            proveedor_id: $request->has('proveedor_id') ? ($request->filled('proveedor_id') ? (int) $request->input('proveedor_id') : null) : null,
            fecha_compra: $request->has('fecha_compra') ? ($request->filled('fecha_compra') ? (string) $request->input('fecha_compra') : null) : null,
            garantia_meses: $request->has('garantia_meses') ? ($request->filled('garantia_meses') ? (int) $request->input('garantia_meses') : null) : null,
            fecha_instalacion: $request->has('fecha_instalacion') ? ($request->filled('fecha_instalacion') ? (string) $request->input('fecha_instalacion') : null) : null,
            vida_util_anos: $request->has('vida_util_anos') ? ($request->filled('vida_util_anos') ? (int) $request->input('vida_util_anos') : null) : null,
            frecuencia_mantenimiento: $request->has('frecuencia_mantenimiento') ? ($request->filled('frecuencia_mantenimiento') ? (string) $request->input('frecuencia_mantenimiento') : null) : null,
            estado_equipo: $request->has('estado_equipo') ? ($request->filled('estado_equipo') ? (string) $request->input('estado_equipo') : null) : null,
            descripcion_general: $request->has('descripcion_general') ? ($request->filled('descripcion_general') ? (string) $request->input('descripcion_general') : null) : null,
            observaciones: $request->has('observaciones') ? ($request->filled('observaciones') ? (string) $request->input('observaciones') : null) : null,
            repuestos_principales: $request->has('repuestos_principales') ? ($request->filled('repuestos_principales') ? (string) $request->input('repuestos_principales') : null) : null,
            recomendaciones: $request->has('recomendaciones') ? ($request->filled('recomendaciones') ? (string) $request->input('recomendaciones') : null) : null,
            equipos_adicionales: $request->has('equipos_adicionales') ? ($request->filled('equipos_adicionales') ? (string) $request->input('equipos_adicionales') : null) : null,
            imagen_url: $imagenUrl,
            eliminar_imagen: $eliminarImagen,
            camposPresentes: $presentes,
        );
    }

    public function toArray(): array
    {
        $data = [];

        // Si se especificaron campos presentes, incluir exactamente esos
        if (!empty($this->camposPresentes)) {
            foreach ($this->camposPresentes as $campo) {
                $data[$campo] = $this->{$campo};
            }
        } else {
            $data = [
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
            ];
        }

        if (!is_null($this->imagen_url)) {
            $data['imagen_url'] = $this->imagen_url;
        }

        return $data;
    }
}
