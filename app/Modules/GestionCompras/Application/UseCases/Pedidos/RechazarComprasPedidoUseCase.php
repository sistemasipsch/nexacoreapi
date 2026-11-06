<?php

namespace App\Modules\GestionCompras\Application\UseCases\Pedidos;

use App\Models\CpPedido;
use Exception;

class RechazarComprasPedidoUseCase
{
    public function execute($id, $motivo)
    {
        $pedido = CpPedido::find($id);
        if (!$pedido) {
            throw new Exception('Pedido no encontrado', 404);
        }

        $pedido->update([
            'estado_compras' => 'rechazado',
            'motivo_rechazado_compras' => $motivo,
            'fecha_compra' => now(),
        ]);
        // Al rechazarse el pedido, todos sus items quedan rechazados
        \App\Models\CpItemPedido::where('cp_pedido', $pedido->id)->update([
            'estado' => 'rechazado',
            'comprado' => 0,
            'fecha_entregado' => null,
        ]);


        return $pedido;
    }
}