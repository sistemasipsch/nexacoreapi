<?php

namespace App\Modules\GestionCompras\Application\UseCases\Pedidos;

use App\Models\CpPedido;
use App\Models\Usuario;
use Exception;

class RechazarGerenciaPedidoUseCase
{
    public function execute($id, $motivo, Usuario $user)
    {
        $pedido = CpPedido::find($id);
        if (!$pedido) {
            throw new Exception('Pedido no encontrado', 404);
        }

        $pedido->update([
            'estado_gerencia' => 'rechazado',
            'responsable_aprobacion' => $user->id,
            'motivo_rechazado_gerencia' => $motivo,
            'fecha_gerencia' => now(),
        ]);

        return $pedido;
    }
}