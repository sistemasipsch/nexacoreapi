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

class RechazarComprasPedidoUseCase
{
    use NotificaPedidosTrait;
    

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

        $this->sendOrderRejectedNotification($pedido, $motivo);

        return $pedido;
    }
}