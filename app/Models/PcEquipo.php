<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PcEquipo extends Model
{
    protected $table = 'pc_equipos';
    public $timestamps = false; // Using custom datetime fields

    protected $fillable = [
        'nombre_equipo',
        'marca',
        'modelo',
        'serial',
        'tipo',
        'propiedad',
        'ip_fija',
        'numero_inventario',
        'sede_id',
        'area_id',
        'responsable_id',
        'estado',
        'fecha_ingreso',
        'imagen_url',
        'fecha_entrega',
        'descripcion_general',
        'garantia_meses',
        'forma_adquisicion',
        'observaciones',
        'repuestos_principales',
        'recomendaciones',
        'equipos_adicionales',
        'creado_por',
        'fecha_creacion'
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_entrega' => 'date',
        'fecha_creacion' => 'datetime',
        'garantia_meses' => 'integer',
        'sede_id' => 'integer',
        'area_id' => 'integer',
        'responsable_id' => 'integer',
        'creado_por' => 'integer',
    ];

    public function getImagenUrlAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, 'data:image')) {
            return $value;
        }

        $clean = ltrim($value, '/');
        if (str_starts_with($clean, 'storage/')) {
            return $clean;
        }

        return 'storage/' . $clean;
    }

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

    public function creador()
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function caracteristicasTecnicas()
    {
        return $this->hasOne(PcCaracteristicasTecnicas::class, 'equipo_id');
    }

    public function licenciasSoftware()
    {
        return $this->hasOne(PcLicenciaSoftware::class, 'equipo_id');
    }

    public function entregas()
    {
        return $this->hasMany(PcEntrega::class, 'equipo_id');
    }

    public function mantenimientos()
    {
        return $this->hasMany(PcMantenimiento::class, 'equipo_id');
    }
}
