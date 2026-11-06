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
            'elaboradoPor:id,nombre_completo,firma_digital', 
            'procesoCompra:id,nombre_completo,firma_digital', 
            'responsableAprobacion:id,nombre_completo,firma_digital', 
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

        if (!empty($filters['sede_id'])) {
            $query->where('sede_id', $filters['sede_id']);
        }

        // Quien puede ver TODOS los pedidos: compras / administración de pedidos.
        // 'cp_pedido.listar' solo habilita entrar al módulo; el usuario ve únicamente sus propios pedidos.
        $canViewAll = $this->permissionService->check($user, 'cp_pedido.listar_todos')
                   || $this->permissionService->check($user, 'cp_pedido.listar.compras');

        if ($canViewAll) {
            return $query->get();
        }

        $esResponsable = $this->permissionService->check($user, 'cp_pedido.listar.responsable');

        return $query->where(function ($q) use ($user, $esResponsable) {
            // Pedidos propios (elaborados o creados por el usuario)
            $q->where('elaborado_por', $user->id)
              ->orWhere('creador_por', $user->id);

            // El responsable además ve los aprobados por compras
            if ($esResponsable) {
                $q->orWhere('estado_compras', 'aprobado');
            }
        })->get();
    }
}