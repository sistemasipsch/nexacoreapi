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

class CrearPedidoUseCase
{
    use HandleSignaturePedidoTrait, NotificaPedidosTrait;
    

    public function execute(array $data, $firmaFile = null, $useStoredSignature = false, Usuario $user)
    {
        DB::beginTransaction();

        try {
            $tipoSolicitud = \App\Models\CpTipoSolicitud::find($data['tipo_solicitud']);
            $esPrioritario = $tipoSolicitud && strtolower($tipoSolicitud->nombre) === 'prioritario';

            if ($esPrioritario) {
                $permissionService = app(PermissionService::class);
                if (!$permissionService->check($user, 'cp_pedido.realizar_pedido_prioritario')) {
                    throw new Exception('No tienes permisos para realizar un pedido prioritario.');
                }
            } else {
                // Validación de horario delegada al servicio de dominio
                app(\App\Modules\GestionCompras\Domain\Services\ValidarHorarioPedidoService::class)->validar();
            }

            $path = $this->handleSignature($firmaFile, $useStoredSignature, $user, 'elaboracion');

            if (empty($path)) {
                throw new Exception('La firma es obligatoria para crear un pedido.');
            }

            // Calculate consecutivo
            $lastConsecutivo = CpPedido::max('consecutivo');
            $nextConsecutivo = $lastConsecutivo ? $lastConsecutivo + 1 : 1;

            /** @var CpPedido $pedido */
            $pedido = CpPedido::create([
                'estado_compras' => 'pendiente',
                'fecha_solicitud' => now('UTC'),
                'proceso_solicitante' => $data['proceso_solicitante'],
                'tipo_solicitud' => $data['tipo_solicitud'],
                'consecutivo' => $nextConsecutivo,
                'observacion' => $data['observacion'] ?? null,
                'sede_id' => $data['sede_id'],
                'elaborado_por' => $data['elaborado_por'],
                'elaborado_por_firma' => 'storage/' . $path,
                'creador_por' => $user->id,
                'pedido_visto' => 0,
                'estado_gerencia' => 'pendiente',
            ]);

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

            $pedido->load(['items', 'solicitante', 'tipoSolicitud', 'sede', 'elaboradoPor', 'creador']);
            $this->sendNewOrderNotification($pedido);

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