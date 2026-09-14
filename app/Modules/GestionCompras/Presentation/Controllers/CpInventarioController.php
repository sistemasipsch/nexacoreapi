<?php

namespace App\Modules\GestionCompras\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Responses\ApiResponse;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

use App\Modules\GestionCompras\Application\UseCases\Inventario\ListarInventarioUseCase;
use App\Modules\GestionCompras\Application\UseCases\Inventario\ObtenerInventarioPorResponsableUseCase;
use App\Modules\GestionCompras\Application\UseCases\Inventario\CrearInventarioUseCase;
use App\Modules\GestionCompras\Application\UseCases\Inventario\ObtenerInventarioUseCase;
use App\Modules\GestionCompras\Application\UseCases\Inventario\ActualizarInventarioUseCase;
use App\Modules\GestionCompras\Application\UseCases\Inventario\EliminarInventarioUseCase;
use App\Modules\GestionCompras\Application\UseCases\Inventario\ExportarInventarioExcelUseCase;
use App\Modules\GestionCompras\Application\UseCases\Inventario\ExportarInventarioPdfUseCase;

class CpInventarioController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ListarInventarioUseCase $listarUseCase,
        protected ObtenerInventarioPorResponsableUseCase $porResponsableUseCase,
        protected CrearInventarioUseCase $crearUseCase,
        protected ObtenerInventarioUseCase $obtenerUseCase,
        protected ActualizarInventarioUseCase $actualizarUseCase,
        protected EliminarInventarioUseCase $eliminarUseCase
    ) {}

    #[OA\Get(
        path: '/api/inventario',
        tags: ['Inventario'],
        summary: 'Listar todos los items de inventario',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Success')
        ]
    )]
    public function index(Request $request)
    {
        try {
            $search = $request->filled('search') ? $request->search : ($request->filled('q') ? $request->q : null);
            $sede_id = $request->filled('sede_id') && $request->sede_id !== 'todas' && $request->sede_id !== 'all' ? $request->sede_id : null;
            $responsable_id = $request->filled('responsable_id') && $request->responsable_id !== 'todos' && $request->responsable_id !== 'all' ? $request->responsable_id : null;
            $coordinador_id = $request->filled('coordinador_id') && $request->coordinador_id !== 'todos' && $request->coordinador_id !== 'all' ? $request->coordinador_id : null;
            $perPage = $request->input('per_page', 100);

            $inventarios = $this->listarUseCase->execute($search, $sede_id, $responsable_id, $coordinador_id, $perPage);

            return ApiResponse::success($inventarios, 'Lista de inventario', 200);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener inventarios: ' . $e->getMessage(), 500);
        }
    }

    public function getByResponsableAndCoordinador(Request $request)
    {
        try {
            $validated = $request->validate([
                'responsable_id' => 'required|integer',
                'coordinador_id' => 'required|integer'
            ]);

            $items = $this->porResponsableUseCase->execute($validated['responsable_id'], $validated['coordinador_id']);

            return response()->json([
                'mensaje' => $items->count() > 0 ? 'Items encontrados' : 'No hay items asignados',
                'objeto' => $items,
                'status' => 200
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'mensaje' => 'Error al buscar items: ' . $e->getMessage(),
                'objeto' => [],
                'status' => 500
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/inventario',
        tags: ['Inventario'],
        summary: 'Crear item de inventario',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Success')
        ]
    )]
    public function store(Request $request)
    {
        $this->permissionService->authorize('inventario.crear');

        $validated = $request->validate([
            'codigo' => 'required|string|max:50|unique:inventario,codigo',
            'nombre' => 'required|string|max:100',
            'dependencia' => 'required|string|max:100',
            'responsable_id' => 'required|exists:personal,id',
            'coordinador_id' => 'required|exists:personal,id',
            'sede_id' => 'required|exists:sedes,id',
            'proceso_id' => 'required|integer',
            'responsable' => 'nullable|string|max:100',
            'marca' => 'nullable|string|max:100',
            'modelo' => 'nullable|string|max:100',
            'serial' => 'nullable|string|max:100',
            'creado_por' => 'nullable|integer',
            'codigo_barras' => 'nullable|string|max:160',
            'num_factu' => 'nullable|string|max:60',
            'grupo' => 'nullable|string|max:60',
            'vida_util' => 'nullable|integer',
            'vida_util_niff' => 'nullable|integer',
            'centro_costo' => 'nullable|string|max:120',
            'ubicacion' => 'nullable|string|max:60',
            'proveedor' => 'nullable|string|max:60',
            'fecha_compra' => 'nullable|date',
            'soporte' => 'nullable|string|max:160',
            'soporte_adjunto' => 'nullable|file|mimes:pdf|max:10240',
            'descripcion' => 'nullable|string|max:160',
            'estado' => 'nullable|string|max:160',
            'escritura' => 'nullable|string|max:255',
            'matricula' => 'nullable|string|max:10',
            'valor_compra' => 'nullable|numeric',
            'salvamenta' => 'nullable|string|max:255',
            'depreciacion' => 'nullable|numeric',
            'depreciacion_niif' => 'nullable|numeric',
            'meses' => 'nullable|string|max:7',
            'meses_niif' => 'nullable|string|max:8',
            'tipo_adquisicion' => 'nullable|string|max:60',
            'calibrado' => 'nullable|date',
            'observaciones' => 'nullable|string',
            'cuenta_inventario' => 'nullable|numeric',
            'cuenta_gasto' => 'nullable|numeric',
            'cuenta_salida' => 'nullable|numeric',
            'grupo_activos' => 'nullable|string|max:60',
            'valor_actual' => 'nullable|numeric',
            'depreciacion_acumulada' => 'nullable|numeric',
            'tipo_bien' => 'nullable|string|max:60',
            'tiene_accesorio' => 'nullable|string|max:10',
            'descripcion_accesorio' => 'nullable|string',
        ]);

        try {
            $inventario = $this->crearUseCase->execute($validated, $request->file('soporte_adjunto'));
            return ApiResponse::success($inventario, 'Inventario creado exitosamente', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al crear inventario: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/inventario/{id}',
        tags: ['Inventario'],
        summary: 'Obtener item de inventario',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Success')
        ]
    )]
    public function show($id)
    {
        $inventario = $this->obtenerUseCase->execute($id);

        if (!$inventario) {
            return ApiResponse::error('Item de inventario no encontrado', 404);
        }

        return ApiResponse::success($inventario, 'Detalles del inventario');
    }

    #[OA\Put(
        path: '/api/inventario/{id}',
        tags: ['Inventario'],
        summary: 'Actualizar item de inventario',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Success')
        ]
    )]
    public function update(Request $request, $id)
    {
        $this->permissionService->authorize('inventario.actualizar');

        $validated = $request->validate([
            'codigo' => 'nullable|string|max:50|unique:inventario,codigo,' . $id,
            'nombre' => 'nullable|string|max:100',
            'dependencia' => 'nullable|string|max:100',
            'responsable' => 'nullable|string|max:100',
            'responsable_id' => 'nullable|exists:personal,id',
            'coordinador_id' => 'nullable|exists:personal,id',
            'marca' => 'nullable|string|max:100',
            'modelo' => 'nullable|string|max:100',
            'serial' => 'nullable|string|max:100',
            'proceso_id' => 'nullable|integer',
            'sede_id' => 'nullable|exists:sedes,id',
            'creado_por' => 'nullable|integer',
            'codigo_barras' => 'nullable|string|max:160',
            'num_factu' => 'nullable|string|max:60',
            'grupo' => 'nullable|string|max:60',
            'vida_util' => 'nullable|integer',
            'vida_util_niff' => 'nullable|integer',
            'centro_costo' => 'nullable|string|max:120',
            'ubicacion' => 'nullable|string|max:60',
            'proveedor' => 'nullable|string|max:60',
            'fecha_compra' => 'nullable|date',
            'soporte' => 'nullable|string|max:160',
            'soporte_adjunto' => 'nullable|string|max:260',
            'descripcion' => 'nullable|string|max:160',
            'estado' => 'nullable|string|max:160',
            'escritura' => 'nullable|string|max:255',
            'matricula' => 'nullable|string|max:10',
            'valor_compra' => 'nullable|numeric',
            'salvamenta' => 'nullable|string|max:255',
            'depreciacion' => 'nullable|numeric',
            'depreciacion_niif' => 'nullable|numeric',
            'meses' => 'nullable|string|max:7',
            'meses_niif' => 'nullable|string|max:8',
            'tipo_adquisicion' => 'nullable|string|max:60',
            'calibrado' => 'nullable|date',
            'observaciones' => 'nullable|string',
            'cuenta_inventario' => 'nullable|numeric',
            'cuenta_gasto' => 'nullable|numeric',
            'cuenta_salida' => 'nullable|numeric',
            'grupo_activos' => 'nullable|string|max:60',
            'valor_actual' => 'nullable|numeric',
            'depreciacion_acumulada' => 'nullable|numeric',
            'tipo_bien' => 'nullable|string|max:60',
            'tiene_accesorio' => 'nullable|string|max:10',
            'descripcion_accesorio' => 'nullable|string',
        ]);

        try {
            $inventario = $this->actualizarUseCase->execute($id, $validated);
            return ApiResponse::success($inventario, 'Inventario actualizado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar inventario: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Delete(
        path: '/api/inventario/{id}',
        tags: ['Inventario'],
        summary: 'Eliminar item de inventario',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Success')
        ]
    )]
    public function destroy($id)
    {
        $this->permissionService->authorize('inventario.eliminar');

        try {
            $this->eliminarUseCase->execute($id);
            return ApiResponse::success(null, 'Inventario eliminado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al eliminar inventario: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/inventario/exportar-excel',
        tags: ['Inventario'],
        summary: 'Exportar inventario a Excel con filtros',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'sede_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'responsable_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'coordinador_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'format', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['stream', 'url'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo Excel generado')
        ]
    )]
    public function exportExcel(Request $request, ExportarInventarioExcelUseCase $useCase)
    {
        try {
            $search = $request->filled('search') ? $request->search : ($request->filled('q') ? $request->q : null);
            $sede_id = $request->filled('sede_id') && $request->sede_id !== 'todas' && $request->sede_id !== 'all' ? $request->sede_id : null;
            $responsable_id = $request->filled('responsable_id') && $request->responsable_id !== 'todos' && $request->responsable_id !== 'all' ? $request->responsable_id : null;
            $coordinador_id = $request->filled('coordinador_id') && $request->coordinador_id !== 'todos' && $request->coordinador_id !== 'all' ? $request->coordinador_id : null;
            $format = $request->query('format', 'stream');

            $result = $useCase->execute($search, $sede_id, $responsable_id, $coordinador_id, $format);

            if (is_array($result) && isset($result['file_url'])) {
                return ApiResponse::success($result, 'Archivo Excel generado con éxito');
            }

            return $result;
        } catch (\Exception $e) {
            \Log::error('Error al exportar inventario a Excel: ' . $e->getMessage());
            return ApiResponse::error('Error al exportar a Excel: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/inventario/exportar-pdf',
        tags: ['Inventario'],
        summary: 'Exportar inventario a PDF con filtros',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'sede_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'responsable_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'coordinador_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'format', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['stream', 'url'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo PDF generado')
        ]
    )]
    public function exportPdf(Request $request, ExportarInventarioPdfUseCase $useCase)
    {
        try {
            $search = $request->filled('search') ? $request->search : ($request->filled('q') ? $request->q : null);
            $sede_id = $request->filled('sede_id') && $request->sede_id !== 'todas' && $request->sede_id !== 'all' ? $request->sede_id : null;
            $responsable_id = $request->filled('responsable_id') && $request->responsable_id !== 'todos' && $request->responsable_id !== 'all' ? $request->responsable_id : null;
            $coordinador_id = $request->filled('coordinador_id') && $request->coordinador_id !== 'todos' && $request->coordinador_id !== 'all' ? $request->coordinador_id : null;
            $format = $request->query('format', 'stream');

            $result = $useCase->execute($search, $sede_id, $responsable_id, $coordinador_id, $format);

            if (is_array($result) && isset($result['file_url'])) {
                return ApiResponse::success($result, 'Archivo PDF generado con éxito');
            }

            return $result;
        } catch (\Exception $e) {
            \Log::error('Error al exportar inventario a PDF: ' . $e->getMessage());
            return ApiResponse::error('Error al exportar a PDF: ' . $e->getMessage(), 500);
        }
    }
}