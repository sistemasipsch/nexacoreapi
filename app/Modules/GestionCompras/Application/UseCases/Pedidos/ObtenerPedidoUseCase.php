<?php

namespace App\Modules\GestionCompras\Application\UseCases\Pedidos;

use App\Models\CpPedido;
use App\Models\CpItemPedido;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Services\PermissionService;

class ObtenerPedidoUseCase
{
    
    

    public function execute($id)
    {
        return CpPedido::with([
            'items.producto', 
            'solicitante', 
            'tipoSolicitud', 
            'sede', 
            'elaboradoPor:id,nombre_completo', 
            'procesoCompra:id,nombre_completo', 
            'responsableAprobacion:id,nombre_completo', 
            'creador:id,nombre_completo'
        ])->find($id);
    }
}