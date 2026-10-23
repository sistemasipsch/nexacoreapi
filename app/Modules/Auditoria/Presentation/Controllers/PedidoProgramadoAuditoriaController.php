<?php

namespace App\Modules\Auditoria\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Responses\ApiResponse;
use App\Services\PermissionService;
use App\Modules\Auditoria\Application\DTOs\FiltroPedidosProgramadosDTO;
use App\Modules\Auditoria\Application\UseCases\PedidosProgramados\ListarPedidosProgramadosUseCase;
use App\Modules\Auditoria\Application\UseCases\PedidosProgramados\ObtenerPedidoProgramadoUseCase;
use App\Modules\Auditoria\Presentation\Requests\ListarPedidosProgramadosRequest;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Auditoría - Pedidos Programados',
    description: 'Consulta y auditoría histórica de pedidos programados'
)]
class PedidoProgramadoAuditoriaController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ListarPedidosProgramadosUseCase $listarUseCase,
        protected ObtenerPedidoProgramadoUseCase $obtenerUseCase
    ) {}

    #[OA\Get(
        path: '/api/auditoria/pedidos-programados',
        summary: 'Listar pedidos programados con filtros y paginación dinámica',
        description: 'Requiere el permiso auditoria.ejecutar',
        tags: ['Auditoría - Pedidos Programados'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'fecha_inicio', in: 'query', description: 'Fecha programada inicial (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'fecha_fin', in: 'query', description: 'Fecha programada final (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'estado', in: 'query', description: 'Estado (programado, ejecutado, cancelado, error)', required: false, schema: new OA\Schema(type: 'string', enum: ['programado', 'ejecutado', 'cancelado', 'error'])),
            new OA\Parameter(name: 'creado_por', in: 'query', description: 'ID del usuario creador', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'buscar', in: 'query', description: 'Texto a buscar en datos de pedido o creador', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', description: 'Número de página actual', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad de registros por página (máximo 100)', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado de pedidos programados obtenido exitosamente'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'No autorizado sin permiso auditoria.ejecutar')
        ]
    )]
    public function index(ListarPedidosProgramadosRequest $request)
    {
        $this->permissionService->authorize('auditoria.ejecutar');

        $filtro = FiltroPedidosProgramadosDTO::fromRequest($request);
        $resultado = $this->listarUseCase->execute($filtro);

        return ApiResponse::success($resultado, 'Listado de pedidos programados obtenido exitosamente');
    }

    #[OA\Get(
        path: '/api/auditoria/pedidos-programados/{id}',
        summary: 'Obtener el detalle de un pedido programado específico',
        description: 'Requiere el permiso auditoria.ejecutar',
        tags: ['Auditoría - Pedidos Programados'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del pedido programado', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle de pedido programado obtenido exitosamente'),
            new OA\Response(response: 404, description: 'Pedido programado no encontrado')
        ]
    )]
    public function show(int $id)
    {
        $this->permissionService->authorize('auditoria.ejecutar');

        try {
            $pedido = $this->obtenerUseCase->execute($id);
            return ApiResponse::success($pedido->toArray(), 'Detalle de pedido programado obtenido exitosamente');
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }
}
