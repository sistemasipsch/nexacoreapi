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

class ListarPedidosUseCase
{
    
    public function __construct(protected PermissionService $permissionService) {}

    public function execute(Usuario $user, array $filters = [])
    {
        $query = CpPedido::with([
            'items.producto', 
            'solicitante', 
            'tipoSolicitud', 
            'sede', 
            'elaboradoPor:id,nombre_completo', 
            'procesoCompra:id,nombre_completo', 
            'responsableAprobacion:id,nombre_completo', 
            'creador:id,nombre_completo'
        ])
            ->orderBy('id', 'desc');

        if (!empty($filters['consecutivo'])) {
            $query->where('consecutivo', $filters['consecutivo']);
        } elseif (!empty($filters['month'])) {
            $query->where('fecha_solicitud', 'like', $filters['month'] . '%');
        }

        if (!empty($filters['estado_compras'])) {
            $query->where('estado_compras', $filters['estado_compras']);
        }

        if (!empty($filters['estado_gerencia'])) {
            $query->where('estado_gerencia', $filters['estado_gerencia']);
        }

        if ($this->permissionService->check($user, 'cp_pedido.listar.compras')) {
            return $query->get();
        } elseif ($this->permissionService->check($user, 'cp_pedido.listar.responsable')) {
            // El responsable normalmente solo ve los aprobados por compras, 
            // pero mantenemos la condición original.
            return $query->where('estado_compras', 'aprobado')->get();
        } else {
            return $query->where('creador_por', $user->id)->get();
        }
    }
}