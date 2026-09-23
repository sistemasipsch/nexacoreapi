<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CpItemPedido extends Model
{
    use HasFactory;

    protected $table = 'cp_items_pedidos';
    public $timestamps = false;

    protected $casts = [
        'fecha_entregado' => 'datetime',
    ];

    protected $fillable = [
        'nombre',
        'cantidad',
        'unidad_medida',
        'referencia_items',
        'cp_pedido',
        'productos_id',
        'comprado',
        'fecha_entregado',
    ];

    public function pedido()
    {
        return $this->belongsTo(CpPedido::class, 'cp_pedido');
    }

    public function producto()
    {
        return $this->belongsTo(CpProducto::class, 'productos_id');
    }
}
