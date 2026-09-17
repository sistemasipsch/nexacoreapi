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

class ActualizarPedidoUseCase
{
    use HandleSignaturePedidoTrait;
    

    public function execute($id, array $data, $firmaFile = null, $useStoredSignature = false, Usuario $user)
    {
        $pedido = CpPedido::findOrFail($id);

        DB::beginTransaction();

        try {
            $tipoSolicitud = \App\Models\CpTipoSolicitud::find($data['tipo_solicitud']);
            $esPrioritario = $tipoSolicitud && stripos($tipoSolicitud->nombre, 'prioritari') !== false;
            if ($esPrioritario) {
                $permissionService = app(PermissionService::class);
                if (!$permissionService->canCreatePriorityOrder($user)) {
                    throw new Exception('No tienes permisos para realizar un pedido prioritario. Esta opción está reservada para coordinadores y usuarios autorizados.');
                }
            }

            $updateData = [
                'proceso_solicitante' => $data['proceso_solicitante'],
                'tipo_solicitud' => $data['tipo_solicitud'],
                'observacion' => $data['observacion'] ?? null,
                'sede_id' => $data['sede_id'],
                'elaborado_por' => $data['elaborado_por'],
            ];

            // Only update signature if provided or requested to use stored
            if ($firmaFile || $useStoredSignature) {
                $path = $this->handleSignature($firmaFile, $useStoredSignature, $user, 'elaboracion_edit');
                if ($path) {
                    $updateData['elaborado_por_firma'] = 'storage/' . $path;
                }
            }

            $pedido->update($updateData);

            // Sync items: Delete and recreate
            $pedido->items()->delete();

            foreach ($data['items'] as $item) {
                CpItemPedido::create([
                    'nombre' => $item['nombre'],
                    'cantidad' => $item['cantidad'],
                    'unidad_medida' => $item['unidad_medida'],
                    'referencia_items' => $item['referencia_items'] ?? null,
                    'cp_pedido' => $pedido->id,
                    'productos_id' => $item['productos_id'],
                    'comprado' => 0,
                ]);
            }

            DB::commit();

            return $pedido->load('items');
        } catch (Exception $e) {
            DB::rollBack();
            if (isset($path) && strpos($path, 'stored') === false) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }
    }
}