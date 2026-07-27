<?php

namespace App\Modules\GestionInfraestructura\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento\ListarMantenimientosUseCase;
use App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento\CrearMantenimientoUseCase;
use App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento\ObtenerMantenimientoUseCase;
use App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento\ActualizarMantenimientoUseCase;
use App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento\EliminarMantenimientoUseCase;
use App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento\ObtenerMantenimientosPorTecnicoUseCase;
use App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento\ObtenerMantenimientosPorCoordinadorUseCase;
use App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento\MarcarMantenimientoRevisadoUseCase;
use App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento\ExportarMantenimientosExcelUseCase;
use App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento\ExportarMisMantenimientosExcelUseCase;
use App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento\ExportarMantenimientosTecnicoExcelUseCase;
use App\Modules\GestionInfraestructura\Application\UseCases\Mantenimiento\ObtenerEstadisticasMantenimientoUseCase;
use App\Services\PermissionService;
use App\Responses\ApiResponse;
use App\Exports\MantenimientoExport;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class MantenimientoController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ListarMantenimientosUseCase $listarUseCase,
        protected CrearMantenimientoUseCase $crearUseCase,
        protected ObtenerMantenimientoUseCase $obtenerUseCase,
        protected ActualizarMantenimientoUseCase $actualizarUseCase,
        protected EliminarMantenimientoUseCase $eliminarUseCase,
        protected ObtenerMantenimientosPorTecnicoUseCase $porTecnicoUseCase,
        protected ObtenerMantenimientosPorCoordinadorUseCase $porCoordinadorUseCase,
        protected MarcarMantenimientoRevisadoUseCase $marcarRevisadoUseCase,
        protected ExportarMantenimientosExcelUseCase $exportarUseCase,
        protected ExportarMisMantenimientosExcelUseCase $exportarMisMantenimientosUseCase,
        protected ExportarMantenimientosTecnicoExcelUseCase $exportarTecnicoUseCase,
        protected ObtenerEstadisticasMantenimientoUseCase $estadisticasUseCase
    ) {}

    public function index()
    {
        $this->permissionService->authorize('mantenimiento.listar');
        return ApiResponse::success($this->listarUseCase->execute(), 'Lista de mantenimientos');
    }

    public function store(Request $request)
    {
        $this->permissionService->authorize('mantenimiento.crear');
        $request->validate([
            'titulo' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:100',
            'modelo' => 'nullable|string|max:100',
            'dependencia' => 'nullable|string|max:255',
            'sede_id' => 'nullable|exists:sedes,id',
            'coordinador_id' => 'nullable|exists:usuarios,id',
            'imagen' => 'nullable|file|image|max:5120',
            'imagen2' => 'nullable|file|image|max:5120',
            'descripcion' => 'nullable|string',
        ]);
        
        $data = $request->except(['imagen', 'imagen2']);

        try {
            return ApiResponse::success($this->crearUseCase->execute($data, $request), 'Mantenimiento creado exitosamente', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al crear mantenimiento: ' . $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        $this->permissionService->authorize('mantenimiento.listar');
        $item = $this->obtenerUseCase->execute($id);
        if (!$item) return ApiResponse::error('Mantenimiento no encontrado', 404);
        return ApiResponse::success($item, 'Detalle del mantenimiento');
    }

    public function update(Request $request, $id)
    {
        $this->permissionService->authorize('mantenimiento.actualizar');
        $request->validate([
            'titulo' => 'nullable|string|max:255',
            'codigo' => 'nullable|string|max:100',
            'modelo' => 'nullable|string|max:100',
            'dependencia' => 'nullable|string|max:255',
            'sede_id' => 'nullable|exists:sedes,id',
            'coordinador_id' => 'nullable|exists:usuarios,id',
            'imagen' => 'nullable|file|image|max:5120',
            'imagen2' => 'nullable|file|image|max:5120',
            'descripcion' => 'nullable|string',
        ]);

        $data = $request->except(['imagen', 'imagen2']);

        try {
            $item = $this->actualizarUseCase->execute($id, $data, $request);
            if (!$item) return ApiResponse::error('Mantenimiento no encontrado', 404);
            return ApiResponse::success($item, 'Mantenimiento actualizado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar mantenimiento: ' . $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        $this->permissionService->authorize('mantenimiento.eliminar');
        if ($this->eliminarUseCase->execute($id)) {
            return ApiResponse::success(null, 'Mantenimiento eliminado exitosamente');
        }
        return ApiResponse::error('Mantenimiento no encontrado o no se pudo eliminar', 404);
    }

    public function misMantenimientos()
    {
        $user = \Illuminate\Support\Facades\Auth::guard('api')->user();

        if ($this->permissionService->check($user, 'mantenimiento.listar_todos')) {
            $mantenimientos = $this->listarUseCase->execute();
            return ApiResponse::success($mantenimientos, 'Todos los mantenimientos');
        }

        if ($this->permissionService->check($user, 'mantenimiento.seleccion_coordinador')) {
            $mantenimientos = $this->porCoordinadorUseCase->execute($user->id);
            return ApiResponse::success($mantenimientos, 'Mantenimientos como coordinador');
        }

        return ApiResponse::success([], 'No tienes registros asignados bajo tu cargo');
    }

    public function exportExcel(Request $request, MantenimientoExport $export)
    {
        $this->permissionService->authorize('mantenimiento.listar');
        $user = \Illuminate\Support\Facades\Auth::guard('api')->user();

        $fechaInicio = $request->query('fecha_inicio');
        $fechaFin = $request->query('fecha_fin');

        return $this->exportarUseCase->execute($fechaInicio, $fechaFin, $user, $this->permissionService, $export);
    }

    #[OA\Get(
        path: '/api/mantenimientos/mis-mantenimientos/exportar-excel',
        tags: ['Mantenimientos'],
        summary: 'Exportar mis mantenimientos a Excel',
        description: 'Exporta los mantenimientos registrados por el usuario que hace la petición. Permite filtrar por fecha_inicio y fecha_fin.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'fecha_inicio', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'fecha_fin', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo Excel de mis mantenimientos'),
            new OA\Response(response: 403, description: 'No autorizado')
        ]
    )]
    public function exportMisMantenimientosExcel(Request $request, MantenimientoExport $export)
    {
        // No verificamos un permiso estricto de listar, cualquier usuario con token puede exportar los suyos.
        // Si hay una autorización básica, se puede agregar: $this->permissionService->authorize('mantenimiento.listar_mis_mantenimientos');
        // Usaremos el auth de JWT directamente:
        $user = \Illuminate\Support\Facades\Auth::guard('api')->user();

        $fechaInicio = $request->query('fecha_inicio');
        $fechaFin = $request->query('fecha_fin');

        return $this->exportarMisMantenimientosUseCase->execute($fechaInicio, $fechaFin, $user, $export);
    }

    #[OA\Get(
        path: '/api/mantenimientos/tecnico/{tecnico_id}/exportar-excel',
        tags: ['Mantenimientos'],
        summary: 'Exportar mantenimientos de un técnico a Excel',
        description: 'Exporta los mantenimientos que tienen asignado a un técnico específico en su agenda. Permite filtrar por fecha_inicio y fecha_fin. Requiere permiso mantenimiento.listar.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'tecnico_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'fecha_inicio', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'fecha_fin', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo Excel de mantenimientos del técnico'),
            new OA\Response(response: 403, description: 'No autorizado')
        ]
    )]
    public function exportMantenimientosTecnicoExcel(Request $request, $tecnicoId, MantenimientoExport $export)
    {
        $this->permissionService->authorize('mantenimiento.listar');
        $user = \Illuminate\Support\Facades\Auth::guard('api')->user();

        $fechaInicio = $request->query('fecha_inicio');
        $fechaFin = $request->query('fecha_fin');

        return $this->exportarTecnicoUseCase->execute($fechaInicio, $fechaFin, $tecnicoId, $user, $export);
    }

    public function getStatistics(Request $request)
    {
        $this->permissionService->authorize('mantenimiento.reportes');
        $stats = $this->estadisticasUseCase->execute();
        return ApiResponse::success($stats, 'Estadísticas obtenidas correctamente');
    }

    public function marcarRevisado($id)
    {
        $this->permissionService->authorize('mantenimiento.marcar_revisado');
        $item = $this->marcarRevisadoUseCase->execute($id);
        if (!$item) return ApiResponse::error('Mantenimiento no encontrado', 404);
        return ApiResponse::success($item, 'Mantenimiento marcado como revisado');
    }
}