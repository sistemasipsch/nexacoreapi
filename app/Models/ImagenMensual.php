<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImagenMensual extends Model
{
    protected $table = 'imagen_mensual';

    protected $fillable = [
        'nombre_original',
        'nombre_archivo',
        'ruta',
        'subido_por',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'subido_por');
    }
}
