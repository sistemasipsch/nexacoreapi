<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PcMantenimiento extends Model
{
    protected $table = 'pc_mantenimientos';
    public $timestamps = false; // Using custom datetime fields

    protected $fillable = [
        'equipo_id',
        'tipo_mantenimiento',
        'descripcion',
        'fecha',
        'empresa_responsable_id',
        'repuesto',
        'cantidad_repuesto',
        'costo_repuesto',
        'nombre_repuesto',
        'responsable_mantenimiento',
        'firma_personal_cargo',
        'firma_sistemas',
        'creado_por',
        'fecha_creacion',
        'estado',
        'cpu',
        'pantalla',
        'teclado',
        'mouse',
        'unidad_cd',
        'foto_antes',
        'foto_despues'
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_creacion' => 'datetime',
        'repuesto' => 'boolean',
        'cantidad_repuesto' => 'integer',
        'costo_repuesto' => 'decimal:0',
        'equipo_id' => 'integer',
        'empresa_responsable_id' => 'integer',
        'creado_por' => 'integer',
        'cpu' => 'boolean',
        'pantalla' => 'boolean',
        'teclado' => 'boolean',
        'mouse' => 'boolean',
        'unidad_cd' => 'boolean',
    ];

    public function equipo()
    {
        return $this->belongsTo(PcEquipo::class, 'equipo_id');
    }

    public function empresaResponsable()
    {
        return $this->belongsTo(DatosEmpresa::class, 'empresa_responsable_id');
    }

    public function creador()
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
 