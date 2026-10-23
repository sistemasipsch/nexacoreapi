<?php

namespace App\Modules\Auditoria\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Responses\ApiResponse;
use App\Services\PermissionService;
use App\Modules\Auditoria\Application\DTOs\FiltroActividadesDTO;
use App\Modules\Auditoria\Application\UseCases\Actividades\ListarActividadesUseCase;
use App\Modules\Auditoria\Application\UseCases\Actividades\ObtenerActividadUseCase;
use App\Modules\Auditoria\Application\UseCases\Actividades\ObtenerEstadisticasActividadesUseCase;
use App\Modules\Auditoria\Presentation\Requests\ListarActividadesRequest;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Auditoría - Actividades',
    description: 'Gestión y consulta de logs y actividades de auditoría del sistema'
)]
class ActividadController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ListarActividadesUseCase $listarUseCase,
        protected ObtenerActividadUseCase $obtenerUseCase,
        protected ObtenerEstadisticasActividadesUseCase $estadisticasUseCase
    ) {}

    #[OA\Get(
        path: '/api/auditoria/actividades',
        summary: 'Listar actividades de auditoría con filtros y paginación dinámica',
        description: 'Requiere el permiso auditoria.ejecutar',
        tags: ['Auditoría - Actividades'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'fecha_inicio', in: 'query', description: 'Fecha inicial (YYYY-MM-DD o YYYY-MM-DD HH:MM:SS)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'fecha_fin', in: 'query', description: 'Fecha final (YYYY-MM-DD o YYYY-MM-DD HH:MM:SS)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'usuario_id', in: 'query', description: 'Filtrar por ID de usuario', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tabla_afectada', in: 'query', description: 'Filtrar por nombre de tabla afectada', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'buscar', in: 'query', description: 'Texto a buscar en acción o datos de usuario', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', description: 'Número de página actual', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad de registros por página (máximo 100)', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado de actividades obtenido exitosamente'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'No autorizado sin permiso auditoria.ejecutar')
        ]
    )]
    public function index(ListarActividadesRequest $request)
    {
        $this->permissionService->authorize('auditoria.ejecutar');

        $filtro = FiltroActividadesDTO::fromRequest($request);
        $resultado = $this->listarUseCase->execute($filtro);

        return ApiResponse::success($resultado, 'Listado de actividades de auditoría obtenido exitosamente');
    }

    #[OA\Get(
        path: '/api/auditoria/actividades/estadisticas',
        summary: 'Obtener estadísticas y resumen de actividades',
        description: 'Requiere el permiso auditoria.ejecutar',
        tags: ['Auditoría - Actividades'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'fecha_inicio', in: 'query', description: 'Fecha inicial para métricas', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'fecha_fin', in: 'query', description: 'Fecha final para métricas', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Estadísticas obtenidas exitosamente')
        ]
    )]
    public function estadisticas(ListarActividadesRequest $request)
    {
        $this->permissionService->authorize('auditoria.ejecutar');

        $filtro = FiltroActividadesDTO::fromRequest($request);
        $estadisticas = $this->estadisticasUseCase->execute($filtro);

        return ApiResponse::success($estadisticas, 'Estadísticas de auditoría obtenidas exitosamente');
    }

    #[OA\Get(
        path: '/api/auditoria/actividades/{id}',
        summary: 'Obtener el detalle de una actividad específica',
        description: 'Requiere el permiso auditoria.ejecutar',
        tags: ['Auditoría - Actividades'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID de la actividad', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle de actividad obtenido exitosamente'),
            new OA\Response(response: 404, description: 'Actividad no encontrada')
        ]
    )]
    public function show(int $id)
    {
        $this->permissionService->authorize('auditoria.ejecutar');

        try {
            $actividad = $this->obtenerUseCase->execute($id);
            return ApiResponse::success($actividad->toArray(), 'Detalle de actividad obtenido exitosamente');
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }
}
