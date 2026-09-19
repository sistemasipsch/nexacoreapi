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
            $isComprado = !empty($itemData['comprado']) && ($itemData['comprado'] === true || $itemData['comprado'] == 1 || $itemData['comprado'] === 'true' || $itemData['comprado'] === '1');

            $currentItem = CpItemPedido::where('id', $itemData['id'])
                ->where('cp_pedido', $id)
                ->first();

            if ($currentItem) {
                // Bloqueo: si ya estaba entregado, no permitir revertir
                if ($currentItem->comprado && !$isComprado) {
                    continue;
                }

                $updateData = ['comprado' => $isComprado ? 1 : 0];
                if ($isComprado && !$currentItem->comprado) {
                    // Guardar la fecha exacta del momento de la confirmación
                    $updateData['fecha_entregado'] = now();
                } elseif ($isComprado) {
                    // Mantener la fecha que ya tenía
                    $updateData['fecha_entregado'] = $currentItem->fecha_entregado ?? now();
                } else {
                    $updateData['fecha_entregado'] = null;
                }

                $currentItem->update($updateData);
            }
        }

        return $pedido->load(['items.producto', 'solicitante', 'tipoSolicitud', 'sede', 'elaboradoPor', 'procesoCompra', 'responsableAprobacion', 'creador']);
    }
}