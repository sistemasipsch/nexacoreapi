<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgendaMantenimiento extends Model
{
    protected $table = 'agenda_mantenimientos';
    public $timestamps = false;

    protected $fillable = [
        'mantenimiento_id',
        'titulo',
        'descripcion',
        'sede_id',
        'fecha_inicio',
        'fecha_fin',
        'tecnico_id',
        'coordinador_id',
        'fecha_creacion',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'fecha_creacion' => 'datetime',
    ];

    public function mantenimiento()
    {
        return $this->belongsTo(Mantenimiento::class, 'mantenimiento_id');
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function tecnico()
    {
        return $this->belongsTo(Usuario::class, 'tecnico_id');
    }

    public function coordinador()
    {
        return $this->belongsTo(Usuario::class, 'coordinador_id');
    }
}
