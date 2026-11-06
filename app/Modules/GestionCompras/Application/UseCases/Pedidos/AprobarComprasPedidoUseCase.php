<?php

namespace App\Modules\GestionCompras\Application\UseCases\Pedidos;

use App\Models\CpPedido;
use App\Models\CpItemPedido;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;
use App\Services\PermissionService;

class AprobarComprasPedidoUseCase
{
    use HandleSignaturePedidoTrait;
    

    public function execute($id, array $data, $firmaFile = null, $useStoredSignature = false, Usuario $user)
    {
        $pedido = CpPedido::find($id);
        if (!$pedido) {
            throw new Exception('Pedido no encontrado', 404);
        }

        $path = $this->handleSignature($firmaFile, $useStoredSignature, $user, 'compra');

        if (empty($path)) {
            throw new Exception('La firma es obligatoria para aprobar el pedido en compras.');
        }

        $pedido->update([
            'estado_compras' => 'aprobado',
            'proceso_compra' => $user->id,
            'proceso_compra_firma' => 'storage/' . $path,
            'motivo_aprobacion_compras' => $data['motivo_aprobacion_compras'] ?? null,

            'color_motivo_compras' => $data['color_motivo_compras'] ?? 'verde',
            'fecha_compra' => now(),
        ]);

        if (isset($data['items_comprados']) && is_array($data['items_comprados'])) {
            CpItemPedido::whereIn('id', $data['items_comprados'])->update([
                'comprado' => 1,

                'estado' => 'entregado',
                'fecha_entregado' => now(),
            ]);
        }

        return $pedido->load(['items.producto', 'solicitante', 'tipoSolicitud', 'sede', 'elaboradoPor', 'procesoCompra', 'responsableAprobacion', 'creador']);
    }
}