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

    protected $appends = ['firma_url'];

    public function getFirmaUrlAttribute()
    {
        if (!$this->firma) {
            return null;
        }
        if (str_starts_with($this->firma, 'http://') || str_starts_with($this->firma, 'https://')) {
            return $this->firma;
        }
        $path = ltrim(str_replace('storage/', '', $this->firma), '/');
        return url('storage/' . $path);
    }

    public function cargo()
    {
        return $this->belongsTo(PCargo::class, 'cargo_id');
    }

    public function pcEntregas()
    {
        return $this->hasMany(PcEntrega::class, 'funcionario_id');
    }

    public function entregasActivos()
    {
        return $this->hasMany(CpEntregaActivosFijos::class, 'personal_id');
    }
}

