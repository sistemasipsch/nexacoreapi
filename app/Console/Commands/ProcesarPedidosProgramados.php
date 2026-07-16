<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\GestionCompras\Domain\Contracts\CpPedidoProgramadoRepositoryInterface;
use App\Models\CpPedido;
use App\Models\CpItemPedido;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\CrearPedidoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\NotificaPedidosTrait;

class ProcesarPedidosProgramados extends Command
{
    use NotificaPedidosTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pedidos:procesar-programados';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa los pedidos programados cuya fecha ya se ha cumplido';

    /**
     * Execute the console command.
     */
    public function handle(CpPedidoProgramadoRepositoryInterface $repository)
    {
        $this->info('Iniciando procesamiento de pedidos programados...');
        
        $hoy = Carbon::now('America/Bogota')->format('Y-m-d H:i:s');
        $pendientes = $repository->obtenerProgramadosPendientes($hoy);

        if (empty($pendientes)) {
            $this->info('No hay pedidos programados pendientes para hoy.');
            return;
        }

        foreach ($pendientes as $pedidoProgramado) {
            $this->info("Procesando pedido programado ID: {$pedidoProgramado->id}");
            
            DB::beginTransaction();
            try {
                $datosReales = is_string($pedidoProgramado->datos_pedido) 
                    ? json_decode($pedidoProgramado->datos_pedido, true) 
                    : $pedidoProgramado->datos_pedido;
                    
                $creador = Usuario::find($pedidoProgramado->creado_por);
                
                $lastConsecutivo = CpPedido::max('consecutivo');
                $nextConsecutivo = $lastConsecutivo ? $lastConsecutivo + 1 : 1;
                
                $rutaFirma = str_replace(url('/'), '', $pedidoProgramado->firma_programador);
                $rutaFirma = ltrim($rutaFirma, '/');
                
                // Algunos entornos retornan solo el path relativo, limpiamos storage/ si viene repetido
                if (!str_starts_with($rutaFirma, 'storage/')) {
                    $rutaFirma = 'storage/' . $rutaFirma;
                }

                $pedidoReal = CpPedido::create([
                    'estado_compras' => 'pendiente',
                    'fecha_solicitud' => now('UTC'),
                    'proceso_solicitante' => $datosReales['proceso_solicitante'] ?? null,
                    'tipo_solicitud' => $datosReales['tipo_solicitud'] ?? null,
                    'consecutivo' => $nextConsecutivo,
                    'observacion' => $datosReales['observacion'] ?? null,
                    'sede_id' => $datosReales['sede_id'] ?? null,
                    'elaborado_por' => $pedidoProgramado->creado_por,
                    'elaborado_por_firma' => $rutaFirma,
                    'creador_por' => $pedidoProgramado->creado_por,
                    'pedido_visto' => 0,
                    'estado_gerencia' => 'pendiente',
                ]);

                if (isset($datosReales['items']) && is_array($datosReales['items'])) {
                    foreach ($datosReales['items'] as $item) {
                        CpItemPedido::create([
                            'nombre' => $item['nombre'] ?? '',
                            'cantidad' => $item['cantidad'] ?? 1,
                            'unidad_medida' => $item['unidad_medida'] ?? 'Unidad',
                            'referencia_items' => empty($item['referencia_items']) ? null : $item['referencia_items'],
                            'cp_pedido' => $pedidoReal->id,
                            'productos_id' => empty($item['productos_id']) ? null : $item['productos_id'],
                            'comprado' => 0,
                        ]);
                    }
                }

                $repository->actualizarEstado($pedidoProgramado->id, 'ejecutado');
                DB::commit();
                
                // Disparamos notificacion reusando el trait
                try {
                    $pedidoReal->load(['items', 'solicitante', 'tipoSolicitud', 'sede', 'elaboradoPor', 'creador']);
                    $this->sendNewOrderNotification($pedidoReal);
                } catch (\Exception $mailEx) {
                    Log::warning("Error enviando email al procesar pedido programado ID: {$pedidoProgramado->id}");
                }

                $this->info("Pedido ID: {$pedidoProgramado->id} ejecutado exitosamente.");
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error procesando pedido programado ID: {$pedidoProgramado->id}. Error: " . $e->getMessage());
                $repository->actualizarEstado($pedidoProgramado->id, 'error');
                $this->error("Error procesando pedido ID: {$pedidoProgramado->id}");
            }
        }

        $this->info('Procesamiento finalizado.');
    }
}
