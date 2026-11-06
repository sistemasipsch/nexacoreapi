<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PcPrestamo extends Model
{
    protected $table = 'pc_prestamos';

    protected $fillable = [
        'equipo_id',
        'descripcion_equipo',
        'serial',
        'titular_nombre',
        'titular_persona_id',
        'prestatario_nombre',
        'prestatario_persona_id',
        'sede_id',
        'fecha_prestamo',
        'fecha_devolucion_esperada',
        'fecha_devolucion_real',
        'prestado_por',
        'recibido_por',
        'estado',
        'observaciones',
        'observaciones_devolucion',
    ];

    protected $casts = [
        'fecha_prestamo' => 'datetime',
        'fecha_devolucion_esperada' => 'datetime',
        'fecha_devolucion_real' => 'datetime',
        'equipo_id' => 'integer',
        'titular_persona_id' => 'integer',
        'prestatario_persona_id' => 'integer',
        'sede_id' => 'integer',
        'prestado_por' => 'integer',
        'recibido_por' => 'integer',
    ];

    public function equipo()
    {
        return $this->belongsTo(PcEquipo::class, 'equipo_id');
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function prestadoPorUsuario()
    {
        return $this->belongsTo(Usuario::class, 'prestado_por');
    }

    public function recibidoPorUsuario()
    {
        return $this->belongsTo(Usuario::class, 'recibido_por');
    }

    public function titularPersona()
    {
        return $this->belongsTo(Personal::class, 'titular_persona_id');
    }

    public function prestatarioPersona()
    {
        return $this->belongsTo(Personal::class, 'prestatario_persona_id');
    }
}
