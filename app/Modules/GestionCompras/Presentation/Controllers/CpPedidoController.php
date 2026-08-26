<?php

namespace App\Modules\GestionCompras\Presentation\Controllers;

use App\Http\Controllers\Controller;

use App\Models\CpPedido;
use App\Models\CpItemPedido;
use App\Models\Usuario;
use App\Services\PermissionService;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\ListarPedidosUseCase;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\ObtenerPedidoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\CrearPedidoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\ActualizarPedidoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\EliminarPedidoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\AprobarComprasPedidoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\RechazarComprasPedidoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\AprobarGerenciaPedidoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\RechazarGerenciaPedidoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\ActualizarItemsPedidoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\CalcularTiempoEntregaPedidoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\ObtenerEstadisticasPedidoUseCase;

use App\Modules\GestionCompras\Application\UseCases\Pedidos\ExportarPedidoExcelUseCase;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\ExportarPedidoPdfUseCase;
use App\Exports\CpConsolidadoExport;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CpPedidoController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ListarPedidosUseCase $listarUseCase,
        protected ObtenerPedidoUseCase $obtenerUseCase,
        protected CrearPedidoUseCase $crearUseCase,
        protected ActualizarPedidoUseCase $actualizarUseCase,
        protected EliminarPedidoUseCase $eliminarUseCase,
        protected AprobarComprasPedidoUseCase $aprobarComprasUseCase,
        protected RechazarComprasPedidoUseCase $rechazarComprasUseCase,
        protected AprobarGerenciaPedidoUseCase $aprobarGerenciaUseCase,
        protected RechazarGerenciaPedidoUseCase $rechazarGerenciaUseCase,
        protected ActualizarItemsPedidoUseCase $actualizarItemsUseCase,
        protected CalcularTiempoEntregaPedidoUseCase $calcularTiempoEntregaUseCase,
        protected ExportarPedidoExcelUseCase $exportarExcelUseCase,
        protected ExportarPedidoPdfUseCase $exportarPdfUseCase,
        protected ObtenerEstadisticasPedidoUseCase $obtenerEstadisticasUseCase
    ) {}

    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/api/gestion-compras/cp-pedidos',
        tags: ['Pedidos de Compra'],
        summary: 'Listar pedidos de compra',
        description: 'Obtiene la lista de pedidos de compra con sus relaciones.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de pedidos', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/CpPedido'))),
            new OA\Response(response: 403, description: 'Prohibido')
        ]
    )]
    public function index(Request $request)
    {
        try {
            $user = auth('api')->user();
            $pedidos = $this->listarUseCase->execute($user, $request->all());
            return ApiResponse::success($pedidos, 'Listado de pedidos obtenido correctamente');
        } catch (\Exception $e) {
            $status = $e->getCode() === 403 ? 403 : 500;
            return ApiResponse::error('Error obteniendo pedidos: ' . $e->getMessage(), $status);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/api/gestion-compras/cp-pedidos',
        tags: ['Pedidos de Compra'],
        summary: 'Crear pedido de compra',
        description: 'Crea un nuevo pedido de compra con sus items.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['proceso_solicitante', 'tipo_solicitud', 'consecutivo', 'sede_id', 'elaborado_por', 'items'],
                    properties: [
                        new OA\Property(property: 'proceso_solicitante', type: 'integer', description: 'ID de la dependencia solicitante'),
                        new OA\Property(property: 'tipo_solicitud', type: 'integer', description: 'ID del tipo de solicitud'),
                        new OA\Property(property: 'observacion', type: 'string', description: 'Observaciones generales'),
                        new OA\Property(property: 'sede_id', type: 'integer', description: 'ID de la sede'),
                        new OA\Property(property: 'elaborado_por', type: 'integer', description: 'ID del usuario que elabora'),
                        new OA\Property(property: 'elaborado_por_firma', type: 'string', format: 'binary', description: 'Archivo de firma (PNG < 1MB)'),
                        new OA\Property(property: 'use_stored_signature', type: 'boolean', description: 'Usar firma guardada del usuario'),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            description: 'Lista de items del pedido',
                            items: new OA\Items(
                                type: 'object',
                                required: ['nombre', 'cantidad', 'unidad_medida', 'productos_id'],
                                properties: [
                                    new OA\Property(property: 'nombre', type: 'string'),
                                    new OA\Property(property: 'cantidad', type: 'integer'),
                                    new OA\Property(property: 'unidad_medida', type: 'string'),
                                    new OA\Property(property: 'referencia_items', type: 'string'),
                                    new OA\Property(property: 'productos_id', type: 'integer')
                                ]
                            )
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pedido creado exitosamente'),
            new OA\Response(response: 422, description: 'Error de validación'),
            new OA\Response(response: 500, description: 'Error del servidor')
        ]
    )]
    public function store(Request $request)
    {
        $this->permissionService->authorize('cp_pedido.crear');
        $validated = $request->validate([
            'proceso_solicitante' => 'required|exists:dependencias_sedes,id',
            'tipo_solicitud' => 'required|exists:cp_tipo_solicitud,id',
            'observacion' => 'required|string',
            'sede_id' => 'required|exists:sedes,id',
            'elaborado_por' => 'required|exists:usuarios,id',
            'use_stored_signature' => 'nullable|boolean',
            'elaborado_por_firma' => 'nullable|file|image|max:1024',
            'items' => 'required|array|min:1',
            'items.*.nombre' => 'required|string|max:255',
            'items.*.cantidad' => 'required|integer|min:1',
            'items.*.unidad_medida' => 'required|string|max:60',
            'items.*.productos_id' => 'nullable|exists:cp_productos,id',
            'items.*.referencia_items' => 'nullable|string',
        ]);

        if (!$request->hasFile('elaborado_por_firma') && !$request->boolean('use_stored_signature')) {
            return response()->json(['error' => 'Debe proporcionar una firma o usar la guardada.'], 400);
        }

        try {
            $pedido = $this->crearUseCase->execute(
                $validated,
                $request->file('elaborado_por_firma'),
                $request->boolean('use_stored_signature'),
                auth()->user()
            );

            return response()->json([
                'message' => 'Pedido creado exitosamente',
                'pedido' => $pedido,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear el pedido: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Post(
        path: '/api/gestion-compras/cp-pedidos/{id}',
        tags: ['Pedidos de Compra'],
        summary: 'Actualizar pedido de compra',
        description: 'Actualiza un pedido de compra existente con sus items.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['proceso_solicitante', 'tipo_solicitud', 'sede_id', 'elaborado_por', 'items'],
                    properties: [
                        new OA\Property(property: 'proceso_solicitante', type: 'integer'),
                        new OA\Property(property: 'tipo_solicitud', type: 'integer'),
                        new OA\Property(property: 'observacion', type: 'string'),
                        new OA\Property(property: 'sede_id', type: 'integer'),
                        new OA\Property(property: 'elaborado_por', type: 'integer'),
                        new OA\Property(property: 'elaborado_por_firma', type: 'string', format: 'binary'),
                        new OA\Property(property: 'use_stored_signature', type: 'boolean'),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                required: ['nombre', 'cantidad', 'unidad_medida'],
                                properties: [
                                    new OA\Property(property: 'nombre', type: 'string'),
                                    new OA\Property(property: 'cantidad', type: 'integer'),
                                    new OA\Property(property: 'unidad_medida', type: 'string'),
                                    new OA\Property(property: 'referencia_items', type: 'string'),
                                    new OA\Property(property: 'productos_id', type: 'integer')
                                ]
                            )
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pedido actualizado exitosamente'),
            new OA\Response(response: 422, description: 'Error de validación')
        ]
    )]
    public function update(Request $request, $id)
    {
        $this->permissionService->authorize('cp_pedido.actualizar');
        $validated = $request->validate([
            'proceso_solicitante' => 'required|exists:dependencias_sedes,id',
            'tipo_solicitud' => 'required|exists:cp_tipo_solicitud,id',
            'observacion' => 'required|string',
            'sede_id' => 'required|exists:sedes,id',
            'elaborado_por' => 'required|exists:usuarios,id',
            'use_stored_signature' => 'nullable|boolean',
            'elaborado_por_firma' => 'nullable|file|image|max:1024',
            'items' => 'required|array|min:1',
            'items.*.nombre' => 'required|string|max:255',
            'items.*.cantidad' => 'required|integer|min:1',
            'items.*.unidad_medida' => 'required|string|max:60',
            'items.*.productos_id' => 'nullable|exists:cp_productos,id',
            'items.*.referencia_items' => 'nullable|string',
        ]);

        try {
            $pedido = $this->actualizarUseCase->execute(
                $id,
                $validated,
                $request->file('elaborado_por_firma'),
                $request->boolean('use_stored_signature'),
                auth()->user()
            );

            return response()->json([
                'message' => 'Pedido actualizado exitosamente',
                'pedido' => $pedido,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el pedido: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/api/gestion-compras/cp-pedidos/{id}',
        tags: ['Pedidos de Compra'],
        summary: 'Obtener pedido de compra',
        description: 'Obtiene los detalles de un pedido específico.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalles del pedido', content: new OA\JsonContent(ref: '#/components/schemas/CpPedido')),
            new OA\Response(response: 404, description: 'Pedido no encontrado')
        ]
    )]
    public function show($id)
    {
        $this->permissionService->authorize('cp_pedido.ver');
        $pedido = $this->obtenerUseCase->execute($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        return response()->json($pedido);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/api/gestion-compras/cp-pedidos/{id}',
        tags: ['Pedidos de Compra'],
        summary: 'Eliminar pedido de compra',
        description: 'Elimina un pedido y sus items asociados.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pedido eliminado'),
            new OA\Response(response: 404, description: 'Pedido no encontrado'),
            new OA\Response(response: 500, description: 'Error del servidor')
        ]
    )]
    public function destroy($id)
    {
        $this->permissionService->authorize('cp_pedido.eliminar');

        try {
            if ($this->eliminarUseCase->execute($id)) {
                return response()->json(['message' => 'Pedido eliminado exitosamente']);
            }
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar el pedido: ' . $e->getMessage()], 500);
        }
    }

    #[OA\Post(
        path: '/api/gestion-compras/cp-pedidos/{id}/aprobar-compras',
        tags: ['Pedidos de Compra'],
        summary: 'Aprobar pedido (Compras)',
        description: 'Aprueba un pedido por parte del área de compras.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: [],
                    properties: [
                        new OA\Property(property: 'motivo_aprobacion_compras', type: 'string', description: 'Motivo de la aprobación por compras (opcional)'),
                        new OA\Property(property: 'proceso_compra_firma', type: 'string', format: 'binary', description: 'Archivo de firma (PNG < 1MB)'),
                        new OA\Property(property: 'use_stored_signature', type: 'boolean', description: 'Usar firma guardada del usuario')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pedido aprobado'),
            new OA\Response(response: 404, description: 'Pedido no encontrado'),
            new OA\Response(response: 400, description: 'Error de validación o falta de firma')
        ]
    )]
    public function aprobarCompras(Request $request, $id)
    {
        $this->permissionService->authorize('cp_pedido.aprobar_compras');
        $validated = $request->validate([
            'motivo_aprobacion_compras' => 'nullable|string',
            'use_stored_signature' => 'nullable|boolean',
            'proceso_compra_firma' => 'nullable|file|image|max:1024',
            'items_comprados' => 'nullable|array',
            'items_comprados.*' => 'exists:cp_items_pedidos,id'
        ]);

        if (!$request->hasFile('proceso_compra_firma') && !$request->boolean('use_stored_signature')) {
            return response()->json(['error' => 'Debe proporcionar una firma o usar la guardada.'], 400);
        }

        try {
            $pedido = $this->aprobarComprasUseCase->execute(
                $id,
                $validated,
                $request->file('proceso_compra_firma'),
                $request->boolean('use_stored_signature'),
                auth()->user()
            );

            return response()->json(['message' => 'Pedido aprobado por compras', 'pedido' => $pedido]);
        } catch (\Exception $e) {
            $status = $e->getCode() === 404 ? 404 : 500;
            return response()->json(['error' => $e->getMessage()], $status);
        }
    }

    #[OA\Post(
        path: '/api/gestion-compras/cp-pedidos/{id}/rechazar-compras',
        tags: ['Pedidos de Compra'],
        summary: 'Rechazar pedido (Compras)',
        description: 'Rechaza un pedido por parte del área de compras.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'motivo_rechazado_compras', type: 'string', description: 'Motivo del rechazo')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pedido rechazado'),
            new OA\Response(response: 404, description: 'Pedido no encontrado')
        ]
    )]
    public function rechazarCompras(Request $request, $id)
    {
        $this->permissionService->authorize('cp_pedido.rechazar_compras');
        $request->validate([
            'motivo_rechazado_compras' => 'nullable|string',
        ]);

        try {
            $pedido = $this->rechazarComprasUseCase->execute($id, $request->motivo_rechazado_compras);
            return response()->json(['message' => 'Pedido rechazado por compras', 'pedido' => $pedido]);
        } catch (\Exception $e) {
            $status = $e->getCode() === 404 ? 404 : 500;
            return response()->json(['error' => $e->getMessage()], $status);
        }
    }

    #[OA\Post(
        path: '/api/gestion-compras/cp-pedidos/{id}/aprobar-gerencia',
        tags: ['Pedidos de Compra'],
        summary: 'Aprobar pedido (Gerencia)',
        description: 'Aprueba un pedido por parte de la gerencia.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'motivo_aprobacion_gerencia', type: 'string', description: 'Observación de aprobación de gerencia (opcional)')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pedido aprobado'),
            new OA\Response(response: 404, description: 'Pedido no encontrado')
        ]
    )]
    public function aprobarGerencia(Request $request, $id)
    {
        $this->permissionService->authorize('cp_pedido.aprobar_gerencia');
        $validated = $request->validate([
            'motivo_aprobacion_gerencia' => 'nullable|string',
            'use_stored_signature' => 'nullable|boolean',
            'responsable_aprobacion_firma' => 'nullable|file|image|max:1024',
        ]);

        if (!$request->hasFile('responsable_aprobacion_firma') && !$request->boolean('use_stored_signature')) {
            return ApiResponse::error('Debe proporcionar una firma o usar la guardada.', 400);
        }

        try {
            $pedido = $this->aprobarGerenciaUseCase->execute(
                $id,
                $validated,
                $request->file('responsable_aprobacion_firma'),
                $request->boolean('use_stored_signature'),
                auth('api')->user()
            );

            return ApiResponse::success($pedido, 'Pedido aprobado por gerencia');
        } catch (\Exception $e) {
            $status = $e->getCode() === 404 ? 404 : 500;
            return ApiResponse::error('Error al aprobar el pedido: ' . $e->getMessage(), $status);
        }
    }

    #[OA\Post(
        path: '/api/gestion-compras/cp-pedidos/{id}/rechazar-gerencia',
        tags: ['Pedidos de Compra'],
        summary: 'Rechazar pedido (Gerencia)',
        description: 'Rechaza un pedido por parte de la gerencia.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'motivo_rechazado_gerencia', type: 'string', description: 'Motivo de rechazo de gerencia (obligatorio)')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pedido rechazado'),
            new OA\Response(response: 404, description: 'Pedido no encontrado')
        ]
    )]
    public function rechazarGerencia(Request $request, $id)
    {
        $this->permissionService->authorize('cp_pedido.rechazar_gerencia');
        $request->validate([
            'motivo_rechazado_gerencia' => 'required|string',
        ]);

        try {
            $pedido = $this->rechazarGerenciaUseCase->execute($id, $request->motivo_rechazado_gerencia, auth('api')->user());
            return response()->json(['message' => 'Pedido rechazado por gerencia', 'pedido' => $pedido]);
        } catch (\Exception $e) {
            $status = $e->getCode() === 404 ? 404 : 500;
            return response()->json(['error' => $e->getMessage()], $status);
        }
    }

    #[OA\Post(
        path: '/api/gestion-compras/cp-pedidos/{id}/update-items',
        tags: ['Pedidos de Compra'],
        summary: 'Actualizar Items (Compras)',
        description: 'Actualiza el estado de compra de los items de un pedido.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'comprado', type: 'integer')
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Items actualizados'),
            new OA\Response(response: 404, description: 'Pedido no encontrado')
        ]
    )]
    public function updateItems(Request $request, $id)
    {
        $this->permissionService->authorize('cp_pedido.actualizar_items');
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:cp_items_pedidos,id',
            'items.*.comprado' => 'required|boolean'
        ]);

        try {
            $pedido = $this->actualizarItemsUseCase->execute($id, $request->items);
            return response()->json([
                'message' => 'Items actualizados correctamente',
                'items' => $pedido->items
            ]);
        } catch (\Exception $e) {
            $status = $e->getCode() === 404 ? 404 : 500;
            return response()->json(['error' => $e->getMessage()], $status);
        }
    }
    public function updateTracking(Request $request, $id)
    {
        $this->permissionService->authorize('cp_pedido.actualizar');

        $data = $request->all();
        foreach ($data as $key => $value) {
            if ($value === '') {
                $data[$key] = null;
            }
        }
        $request->merge($data);

        $validated = $request->validate([
            'fecha_solicitud_cotizacion' => 'nullable|string',
            'fecha_respuesta_cotizacion' => 'nullable|string',
            'fecha_envio_proveedor' => 'nullable|string',
            'observaciones_pedidos' => 'nullable|string',
        ]);

        try {
            $pedido = CpPedido::find($id);
            if (!$pedido) {
                return ApiResponse::error('Pedido no encontrado', 404);
            }

            $pedido->update($validated);
            $pedido->load(['items', 'solicitante', 'tipoSolicitud', 'sede', 'elaboradoPor', 'procesoCompra', 'responsableAprobacion', 'creador']);

            return ApiResponse::success($pedido, 'Seguimiento actualizado correctamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar seguimiento: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export a pedido to Excel.
     */
    #[OA\Get(
        path: '/api/gestion-compras/cp-pedidos/{id}/exportar-excel',
        tags: ['Pedidos de Compra'],
        summary: 'Exportar pedido a Excel',
        description: 'Genera y descarga un archivo Excel con los datos del pedido.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo Excel descargado'),
            new OA\Response(response: 404, description: 'Pedido no encontrado'),
            new OA\Response(response: 500, description: 'Error del servidor')
        ]
    )]
    public function exportExcel($id)
    {
        $this->permissionService->authorize('cp_pedido.ver');

        try {
            return $this->exportarExcelUseCase->execute((int) $id);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al exportar pedido: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export a pedido to PDF.
     */
    #[OA\Get(
        path: '/api/gestion-compras/cp-pedidos/{id}/exportar-pdf',
        tags: ['Pedidos de Compra'],
        summary: 'Exportar pedido a PDF',
        description: 'Genera y descarga un archivo PDF con los datos del pedido, replicando la plantilla oficial.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo PDF descargado'),
            new OA\Response(response: 404, description: 'Pedido no encontrado'),
            new OA\Response(response: 500, description: 'Error del servidor')
        ]
    )]
    public function exportPdf($id)
    {
        try {
            return $this->exportarPdfUseCase->execute((int) $id);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al exportar pedido a PDF: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export a consolidated report of pedidos to Excel.
     */
    #[OA\Post(
        path: '/api/gestion-compras/cp-pedidos/exportar-consolidado',
        tags: ['Pedidos de Compra'],
        summary: 'Exportar consolidado de pedidos a Excel',
        description: 'Genera y descarga un archivo Excel con el consolidado de pedidos basado en filtros.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'fecha_desde', type: 'string', format: 'date'),
                    new OA\Property(property: 'fecha_hasta', type: 'string', format: 'date'),
                    new OA\Property(property: 'sede_id', type: 'integer'),
                    new OA\Property(property: 'proceso', type: 'string'),
                    new OA\Property(property: 'elaborado_por', type: 'integer'),
                    new OA\Property(property: 'search', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Archivo Excel descargado'),
            new OA\Response(response: 500, description: 'Error del servidor')
        ]
    )]
    public function exportConsolidadoExcel(Request $request)
    {
        $this->permissionService->authorize('cp_pedido.listar');

        try {
            $export = new CpConsolidadoExport();
            return $export->generate($request->all());
        } catch (\Exception $e) {
            return ApiResponse::error('Error al exportar consolidado: ' . $e->getMessage(), 500);
        }
    }
 
    public function calcularTiempoEntregaPedido($id)
    {
        try {
            $pedido = CpPedido::find($id);
            if (!$pedido) {
                return ApiResponse::error('Pedido no encontrado', 404);
            }
            $tiempos = $this->calcularTiempoEntregaUseCase->execute($pedido);
            return ApiResponse::success($tiempos, 'Tiempos de entrega del pedido calculados exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al calcular tiempos del pedido: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/gestion-compras/cp-pedidos/{id}/estadisticas',
        tags: ['Pedidos de Compra'],
        summary: 'Obtener estadísticas de un pedido',
        description: 'Obtiene los cálculos de SLA y tiempos de cumplimiento de un pedido específico.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estadísticas del pedido calculadas exitosamente'),
            new OA\Response(response: 404, description: 'Pedido no encontrado'),
            new OA\Response(response: 500, description: 'Error del servidor')
        ]
    )]
    public function obtenerEstadisticas($id)
    {
        try {
            $estadisticas = $this->obtenerEstadisticasUseCase->execute($id);
            return ApiResponse::success($estadisticas, 'Estadísticas del pedido obtenidas exitosamente');
        } catch (\Exception $e) {
            $status = $e->getCode() === 404 ? 404 : 500;
            return ApiResponse::error('Error al obtener estadísticas: ' . $e->getMessage(), $status);
        }
    }
}
 