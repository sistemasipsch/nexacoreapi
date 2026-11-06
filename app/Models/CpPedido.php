<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CpPedido',
    description: 'Modelo de Pedido de Compra',
    required: ['id', 'estado_compras', 'fecha_solicitud', 'proceso_solicitante', 'tipo_solicitud', 'consecutivo', 'sede_id', 'elaborado_por'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'ID del pedido'),
        new OA\Property(property: 'estado_compras', type: 'string', enum: ['pendiente', 'aprobado', 'rechazado', 'en proceso'], description: 'Estado de compras'),
        new OA\Property(property: 'fecha_solicitud', type: 'string', format: 'date', description: 'Fecha de solicitud'),
        new OA\Property(property: 'proceso_solicitante', type: 'integer', description: 'ID de la dependencia solicitante'),
        new OA\Property(property: 'tipo_solicitud', type: 'integer', description: 'ID del tipo de solicitud'),
        new OA\Property(property: 'consecutivo', type: 'integer', description: 'Consecutivo único'),
        new OA\Property(property: 'observacion', type: 'string', description: 'Observaciones'),
        new OA\Property(property: 'sede_id', type: 'integer', description: 'ID de la sede'),
        new OA\Property(property: 'elaborado_por', type: 'integer', description: 'ID del usuario que elaboró'),
        new OA\Property(property: 'elaborado_por_firma', type: 'string', description: 'Ruta de la firma'),
        new OA\Property(
            property: 'items',
            type: 'array',
            description: 'Items del pedido',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'nombre', type: 'string'),
                    new OA\Property(property: 'cantidad', type: 'integer'),
                    new OA\Property(property: 'unidad_medida', type: 'string'),
                    new OA\Property(property: 'comprado', type: 'integer')
                ]
            )
        )
    ]
)]
class CpPedido extends Model
{
    use HasFactory;

    protected $table = 'cp_pedidos';
    public $timestamps = false;

    protected $casts = [
        'fecha_solicitud' => 'datetime',
        'fecha_compra' => 'datetime',
        'fecha_gerencia' => 'datetime',
    ];

    protected $fillable = [
        'estado_compras',
        'fecha_solicitud',
        'proceso_solicitante',
        'tipo_solicitud',
        'consecutivo',
        'observacion',
        'sede_id',
        'elaborado_por',
        'elaborado_por_firma',
        'proceso_compra',
        'proceso_compra_firma',
        'responsable_aprobacion',
        'responsable_aprobacion_firma',
        'motivo_aprobacion_compras',
        'color_motivo_compras',
        'creador_por',
        'pedido_visto',
        'observacion_diligenciado',
        'estado_gerencia',
        'motivo_rechazado_compras',
        'adjunto_pdf',
        'fecha_compra',
        'fecha_solicitud_cotizacion',
        'fecha_respuesta_cotizacion',
        'firma_aprobacion_orden',
        'fecha_envio_proveedor',
        'fecha_gerencia',
        'motivo_aprobacion_gerencia',
        'motivo_rechazado_gerencia',
        'motivo_aprobacion',
        'observaciones_pedidos',
        'observacion_gerencia',
    ];

    public function items()
    {
        return $this->hasMany(CpItemPedido::class, 'cp_pedido');
    }

    public function solicitante()
    {
        return $this->belongsTo(DependenciaSede::class, 'proceso_solicitante');
    }

    public function tipoSolicitud()
    {
        return $this->belongsTo(CpTipoSolicitud::class, 'tipo_solicitud');
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function elaboradoPor()
    {
        return $this->belongsTo(Usuario::class, 'elaborado_por');
    }

    public function procesoCompra()
    {
        return $this->belongsTo(Usuario::class, 'proceso_compra');
    }

    public function responsableAprobacion()
    {
        return $this->belongsTo(Usuario::class, 'responsable_aprobacion');
    }

    public function getElaboradoPorFirmaAttribute($value)
    {
        if (!$value) return null;
        $path = str_replace(['storage/', 'public/'], '', $value);
        return url('storage/' . $path);
    }

    public function getProcesoCompraFirmaAttribute($value)
    {
        if (!$value) return null;
        $path = str_replace(['storage/', 'public/'], '', $value);
        return url('storage/' . $path);
    }

    public function getResponsableAprobacionFirmaAttribute($value)
    {
        if (!$value) return null;
        $path = str_replace(['storage/', 'public/'], '', $value);
        return url('storage/' . $path);
    }

    public function creador()
    {
        return $this->belongsTo(Usuario::class, 'creador_por');
    }
}
