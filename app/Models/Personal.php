<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Personal extends Model
{
    protected $table = 'personal';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'cedula',
        'telefono',
        'cargo_id',
        'firma',
        'estado',
    ];

    public function cargo()
    {
        return $this->belongsTo(PCargo::class, 'cargo_id');
    }

    public function pcEntregas()
    {
        return $this->hasMany(PcEntrega::class, 'funcionario_id');
    }
}
