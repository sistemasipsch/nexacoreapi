<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    protected $table = 'sedes';
    public $timestamps = false; // sedes table in migration does not have timestamps
    protected $fillable = ['nombre', 'responsable_id'];

    /** Usuario responsable de la sede (firma como "personal a cargo" en mantenimientos) */
    public function responsable()
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }
}
