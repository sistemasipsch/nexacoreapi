<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfraEquipo extends Model
{
    protected $table = 'inventario_infraestructura';

    protected $fillable = [
        'nombre_equipo',
        'tipo_equipo',
        'marca',
        'modelo',
        'serial',
        'activo_fijo',
        'imagen_url',
        'capacidad',
        'voltaje',
        'amperaje',
        'frecuencia',
        'sede_id',
        'area_id',
        'ubicacion_especifica',
        'responsable_id',
        'forma_adquisicion',
        'proveedor_id',
        'fecha_compra',
        'garantia_meses',
        'fecha_instalacion',
        'vida_util_anos',
        'frecuencia_mantenimiento',
        'estado_equipo',
        'fecha_traslado',
        'traslado_dependencia_id',
        'traslado_area_id',
        'descripcion_general',
        'observaciones',
        'repuestos_principales',
        'recomendaciones',
        'equipos_adicionales',
        'creado_por',
    ];

    protected $casts = [
        'fecha_compra' => 'date',
        'fecha_instalacion' => 'date',
        'fecha_traslado' => 'date',
        'garantia_meses' => 'integer',
        'vida_util_anos' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relaciones Eloquent

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function responsable()
    {
        return $this->belongsTo(Personal::class, 'responsable_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(CpProveedor::class, 'proveedor_id');
    }

    public function trasladoDependencia()
    {
        return $this->belongsTo(DependenciaSede::class, 'traslado_dependencia_id');
    }

    public function trasladoArea()
    {
        return $this->belongsTo(Area::class, 'traslado_area_id');
    }

    public function creador()
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function mantenimientos()
    {
        return $this->hasMany(Mantenimiento::class, 'equipo_infraestructura_id')->orderBy('id', 'desc');
    }
}
