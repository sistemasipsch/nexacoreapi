<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CpPedidoProgramado extends Model
{
    use HasFactory;

    protected $table = 'cp_pedidos_programados';

    protected $fillable = [
        'datos_pedido',
        'fecha_programada',
        'firma_programador',
        'creado_por',
        'estado'
    ];

    protected $casts = [
        'datos_pedido' => 'array',
    ];

    public function creador()
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function getFirmaProgramadorAttribute($value)
    {
        if (!$value) return null;
        $path = str_replace(['storage/', 'public/'], '', $value);
        return url('storage/' . $path);
    }

    public function getFechaProgramadaAttribute($value)
    {
        if (!$value) return null;
        
        // La fecha en BD está guardada en America/Bogota.
        // Al enviarla (ej. a una API JSON), la convertimos a formato UTC ISO8601
        // para que el frontend no le aplique un desfase doble.
        return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $value, 'America/Bogota')
            ->setTimezone('UTC')
            ->format('Y-m-d\TH:i:s.u\Z');
    }
}
