<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mantenimiento extends Model
{
    protected $table = 'mantenimientos';
    public $timestamps = false;

    protected $fillable = [
        'titulo',
        'codigo',
        'serial',
        'modelo',
        'dependencia',
        'sede_id',
        'area_id',
        'equipo_infraestructura_id',
        'coordinador_id',
        'imagen',
        'descripcion',
        'revisado_por',
        'fecha_revisado',
        'creado_por',
        'fecha_creacion',
        'esta_revisado',
        'fecha_ultima_actualizacion',
    ];

    protected $casts = [
        'fecha_revisado' => 'datetime',
        'fecha_creacion' => 'datetime',
        'fecha_ultima_actualizacion' => 'datetime',
        'esta_revisado' => 'boolean',
    ];

    // Relationships

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function coordinador()
    {
        return $this->belongsTo(Usuario::class, 'coordinador_id');
    }

    public function revisador()
    {
        return $this->belongsTo(Usuario::class, 'revisado_por');
    }

    public function creador()
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function agendas()
    {
        return $this->hasMany(AgendaMantenimiento::class, 'mantenimiento_id');
    }

    public function equipoInfraestructura()
    {
        return $this->belongsTo(InfraEquipo::class, 'equipo_infraestructura_id');
    }
}
