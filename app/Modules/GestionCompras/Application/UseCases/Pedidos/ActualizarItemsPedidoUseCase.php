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

class ActualizarItemsPedidoUseCase
{
    
    

    public function execute($id, array $items)
    {
        $pedido = CpPedido::find($id);
        if (!$pedido) {
            throw new Exception('Pedido no encontrado', 404);
        }

        foreach ($items as $itemData) {
            // Estado solicitado: pendiente | procesado | entregado (compatibilidad: comprado=true => entregado)
            $nuevoEstado = $itemData['estado'] ?? null;
            if (!$nuevoEstado) {
                $isComprado = !empty($itemData['comprado']) && ($itemData['comprado'] === true || $itemData['comprado'] == 1 || $itemData['comprado'] === 'true' || $itemData['comprado'] === '1');
                $nuevoEstado = $isComprado ? 'entregado' : 'pendiente';
            }

            $currentItem = CpItemPedido::where('id', $itemData['id'])
                ->where('cp_pedido', $id)
                ->first();

            if (!$currentItem) {
                continue;
            }

            $estadoActual = $currentItem->comprado ? 'entregado' : ($currentItem->estado ?: 'pendiente');

            // Flujo permitido: pendiente -> procesado | entregado. Procesado y entregado son estados finales.
            if ($estadoActual !== 'pendiente' || !in_array($nuevoEstado, ['procesado', 'entregado'], true)) {
                continue;
            }

            $updateData = [
                'estado' => $nuevoEstado,
                'comprado' => $nuevoEstado === 'entregado' ? 1 : 0,
            ];
            if ($nuevoEstado === 'entregado') {
                $updateData['fecha_entregado'] = now();
            }

            $currentItem->update($updateData);
        }
        return $pedido->load(['items.producto', 'solicitante', 'tipoSolicitud', 'sede', 'elaboradoPor', 'procesoCompra', 'responsableAprobacion', 'creador']);
    }
}