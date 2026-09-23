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

class RechazarGerenciaPedidoUseCase
{
    use NotificaPedidosTrait;
    

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

        $this->sendGerenciaRejectedNotification($pedido, $motivo);

        return $pedido;
    }
}