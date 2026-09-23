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

class AprobarGerenciaPedidoUseCase
{
    use HandleSignaturePedidoTrait, NotificaPedidosTrait;
    

    public function execute($id, array $data, $firmaFile = null, $useStoredSignature = false, Usuario $user)
    {
        $pedido = CpPedido::find($id);
        if (!$pedido) {
            throw new Exception('Pedido no encontrado', 404);
        }

        $path = $this->handleSignature($firmaFile, $useStoredSignature, $user, 'gerencia');

        if (empty($path)) {
            throw new Exception('La firma es obligatoria para aprobar el pedido en gerencia.');
        }

        $pedido->update([
            'estado_gerencia' => 'aprobado',
            'responsable_aprobacion' => $user->id,
            'responsable_aprobacion_firma' => 'storage/' . $path,
            'fecha_gerencia' => now(),
            'motivo_aprobacion_gerencia' => $data['motivo_aprobacion_gerencia'] ?? null,
        ]);

        $this->sendGerenciaApprovedNotification($pedido);

        return $pedido;
    }
}