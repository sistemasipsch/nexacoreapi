<?php

namespace App\Modules\GestionSistemas\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Responses\ApiResponse;
use App\Services\PermissionService;
use App\Services\PcMantenimientoFirmaService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

use App\Modules\GestionSistemas\Infrastructure\Repositories\PcMantenimientoRepository;
use App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos\CrearMantenimientoEquipoUseCase;
use App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos\ActualizarMantenimientoEquipoUseCase;
use App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos\EliminarMantenimientoEquipoUseCase;
use App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos\ObtenerMantenimientoEquipoUseCase;
use App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos\ExportarMantenimientoEquipoExcelUseCase;
use App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos\ExportarMantenimientoEquipoPdfUseCase;
use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;

class PcMantenimientoController extends Controller
{
    private PcMantenimientoRepository $repository;
    private PermissionService $permissionService;
    private PcMantenimientoFirmaService $firmaService;

    public function __construct(PermissionService $permissionService, PcMantenimientoFirmaService $firmaService)
    {
        $this->repository = new PcMantenimientoRepository();
        $this->permissionService = $permissionService;
        $this->firmaService = $firmaService;
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/pc-mantenimientos',
        tags: ['PcMantenimientos (DDD)'],
        summary: 'Listar mantenimientos',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista obtenida', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 403, description: 'Prohibido')
        ]
    )]
    public function index()
    {
        $items = $this->repository->getAll();
        return ApiResponse::success($items, 'Mantenimientos listados exitosamente');
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/pc-mantenimientos/cronograma',
        tags: ['PcMantenimientos (DDD)'],
        summary: 'Obtener cronograma de mantenimientos',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Cronograma obtenido exitosamente', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))
        ]
    )]
    public function cronograma()
    {
        $useCase = new \App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos\ObtenerCronogramaMantenimientosUseCase();
        $cronograma = $useCase->execute();
        return ApiResponse::success($cronograma, 'Cronograma de mantenimientos obtenido exitosamente');
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/pc-mantenimientos/{id}',
        tags: ['PcMantenimientos (DDD)'],
        summary: 'Obtener mantenimiento',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detalle', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 404, description: 'No encontrado'),
            new OA\Response(response: 403, description: 'Prohibido')
        ]
    )]
    public function show($id)
    {
        try {
            $useCase = new ObtenerMantenimientoEquipoUseCase($this->repository);
            $item = $useCase->execute($id);
            return ApiResponse::success($item, 'Detalle del mantenimiento');
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 500;
            return ApiResponse::error($e->getMessage(), $code);
        }
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/pc-mantenimientos/equipo/{equipo_id}',
        tags: ['PcMantenimientos (DDD)'],
        summary: 'Listar mantenimientos por equipo',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'equipo_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Lista obtenida', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 404, description: 'No encontrado')
        ]
    )]
    public function showByEquipo($equipo_id)
    {
        $items = $this->repository->getByEquipo($equipo_id);
        return ApiResponse::success($items, 'Historial de mantenimientos del equipo');
    }

    #[OA\Post(
        path: '/api/gestion-sistemas/pc-mantenimientos',
        tags: ['PcMantenimientos (DDD)'],
        summary: 'Crear mantenimiento',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'equipo_id', type: 'integer'),
                    new OA\Property(property: 'tipo_mantenimiento', type: 'string', enum: ['preventivo', 'correctivo']),
                    new OA\Property(property: 'descripcion', type: 'string'),
                    new OA\Property(property: 'cpu', type: 'boolean'),
                    new OA\Property(property: 'pantalla', type: 'boolean'),
                    new OA\Property(property: 'teclado', type: 'boolean'),
                    new OA\Property(property: 'mouse', type: 'boolean'),
                    new OA\Property(property: 'unidad_cd', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado exitosamente', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 403, description: 'Prohibido')
        ]
    )]
    public function store(Request $request)
    {
        $this->permissionService->authorize("pc_mantenimiento.crear");
        $validated = $request->validate([
            'equipo_id' => 'required|integer|exists:pc_equipos,id',
            'tipo_mantenimiento' => 'nullable|in:preventivo,correctivo',
            'descripcion' => 'nullable|string',
            'fecha' => 'nullable|date',
            'empresa_responsable_id' => 'nullable|integer|exists:datos_empresa,id',
            'repuesto' => 'nullable|in:true,false,1,0,"true","false"',
            'cantidad_repuesto' => 'nullable|integer',
            'costo_repuesto' => 'nullable|numeric',
            'nombre_repuesto' => 'nullable|string|max:255',
            'responsable_mantenimiento' => 'nullable|string|max:255',
            'estado' => 'nullable|in:completado,pendiente',
            'firma_personal_cargo' => 'nullable|string',
            'use_stored_signature_sistemas' => 'nullable|in:true,false,1,0,"true","false"',
            'firma_sistemas' => 'required_without:use_stored_signature_sistemas|string|nullable', 
            'cpu' => 'nullable|in:true,false,1,0,"true","false"',
            'pantalla' => 'nullable|in:true,false,1,0,"true","false"',
            'teclado' => 'nullable|in:true,false,1,0,"true","false"',
            'mouse' => 'nullable|in:true,false,1,0,"true","false"',
            'unidad_cd' => 'nullable|in:true,false,1,0,"true","false"',
            'foto_antes' => 'nullable|file|image|max:20480',
            'foto_despues' => 'nullable|file|image|max:20480',
        ]);

        try {
            if (auth()->check()) {
                $validated['creado_por'] = auth()->id();
                $validated['responsable_mantenimiento'] = auth()->user()->nombre_completo;
            }

            $useCase = new CrearMantenimientoEquipoUseCase($this->repository, $this->firmaService);
            $item = $useCase->execute($validated);
            
            return ApiResponse::success($item, 'Mantenimiento creado exitosamente', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al crear mantenimiento: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Put(
        path: '/api/gestion-sistemas/pc-mantenimientos/{id}',
        tags: ['PcMantenimientos (DDD)'],
        summary: 'Actualizar mantenimiento',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'descripcion', type: 'string'),
                    new OA\Property(property: 'estado', type: 'string', enum: ['completado', 'pendiente']),
                    new OA\Property(property: 'cpu', type: 'boolean'),
                    new OA\Property(property: 'pantalla', type: 'boolean'),
                    new OA\Property(property: 'teclado', type: 'boolean'),
                    new OA\Property(property: 'mouse', type: 'boolean'),
                    new OA\Property(property: 'unidad_cd', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Actualizado', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 404, description: 'No encontrado'),
            new OA\Response(response: 403, description: 'Prohibido')
        ]
    )]
    public function update(Request $request, $id)
    {
        $this->permissionService->authorize("pc_mantenimientos.crud");
        $item = $this->repository->find($id);
        if (!$item) {
            return ApiResponse::error('Mantenimiento no encontrado', 404);
        }

        $validated = $request->validate([
            'equipo_id' => 'sometimes|integer|exists:pc_equipos,id',
            'tipo_mantenimiento' => 'nullable|in:preventivo,correctivo',
            'descripcion' => 'nullable|string',
            'fecha' => 'nullable|date',
            'empresa_responsable_id' => 'nullable|integer|exists:datos_empresa,id',
            'repuesto' => 'nullable|in:true,false,1,0,"true","false"',
            'cantidad_repuesto' => 'nullable|integer',
            'costo_repuesto' => 'nullable|numeric',
            'nombre_repuesto' => 'nullable|string|max:255',
            'responsable_mantenimiento' => 'nullable|string|max:255',
            'estado' => 'nullable|in:completado,pendiente',
            'cpu' => 'nullable|in:true,false,1,0,"true","false"',
            'pantalla' => 'nullable|in:true,false,1,0,"true","false"',
            'teclado' => 'nullable|in:true,false,1,0,"true","false"',
            'mouse' => 'nullable|in:true,false,1,0,"true","false"',
            'unidad_cd' => 'nullable|in:true,false,1,0,"true","false"',
            'foto_antes' => 'nullable|file|image|max:20480',
            'foto_despues' => 'nullable|file|image|max:20480',
        ]);

        try {
            $useCase = new ActualizarMantenimientoEquipoUseCase($this->repository, $this->firmaService);
            $updated = $useCase->execute($id, $validated);
            return ApiResponse::success($updated, 'Mantenimiento actualizado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar mantenimiento: ' . $e->getMessage(), 500);
        }
    }

    public function actualizarFirmas(Request $request, $id)
    {
        $validated = $request->validate([
            'firma_personal_cargo' => 'nullable|string',
            'firma_sistemas' => 'nullable|string',
            'estado' => 'nullable|in:completado,pendiente',
        ]);

        $item = $this->repository->find($id);
        if (!$item) {
            return ApiResponse::error('Mantenimiento no encontrado', 404);
        }

        try {
            $useCase = new ActualizarMantenimientoEquipoUseCase($this->repository, $this->firmaService);
            $updated = $useCase->execute($id, $validated);
            return ApiResponse::success($updated, 'Datos actualizados correctamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar firmas: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Delete(
        path: '/api/gestion-sistemas/pc-mantenimientos/{id}',
        tags: ['PcMantenimientos (DDD)'],
        summary: 'Eliminar mantenimiento',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Eliminado', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 404, description: 'No encontrado'),
            new OA\Response(response: 403, description: 'Prohibido')
        ]
    )]
    public function destroy($id)
    {
        $this->permissionService->authorize("pc_mantenimiento.eliminar");
        
        $useCase = new EliminarMantenimientoEquipoUseCase($this->repository);
        if ($useCase->execute($id)) {
            return ApiResponse::success(null, 'Mantenimiento eliminado exitosamente');
        }

        return ApiResponse::error('Mantenimiento no encontrado o no se pudo eliminar', 404);
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/pc-mantenimientos/{id}/exportar-excel',
        tags: ['PcMantenimientos (DDD)'],
        summary: 'Exportar mantenimiento a Excel',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Archivo Excel generado', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 404, description: 'No encontrado'),
            new OA\Response(response: 403, description: 'Prohibido')
        ]
    )]
    public function exportarExcel($id)
    {
        $this->permissionService->authorize("pc_mantenimiento.crear"); // or similar permission
        try {
            $useCase = new ExportarMantenimientoEquipoExcelUseCase($this->repository);
            $fileName = $useCase->execute($id);
            $url = asset('storage/exports/' . $fileName);
            
            return response()->json([
                'success' => true,
                'message' => 'Archivo Excel generado con éxito.',
                'data' => [
                    'file_url' => $url,
                    'file_name' => $fileName
                ]
            ], 200);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar acta a Excel: ' . $e->getMessage()
            ], $code);
        }
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/pc-mantenimientos/{id}/exportar-pdf',
        tags: ['PcMantenimientos (DDD)'],
        summary: 'Exportar mantenimiento a PDF',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Archivo PDF generado', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 404, description: 'No encontrado'),
            new OA\Response(response: 403, description: 'Prohibido')
        ]
    )]
    public function exportarPdf($id, ExcelToPdfConverterInterface $pdfConverter)
    {
        $this->permissionService->authorize("pc_mantenimiento.crear"); // or similar permission
        try {
            $useCase = new ExportarMantenimientoEquipoPdfUseCase($pdfConverter);
            $fileName = $useCase->execute($id);
            $url = asset('storage/exports/' . $fileName);
            
            return response()->json([
                'success' => true,
                'message' => 'Archivo PDF generado con éxito.',
                'data' => [
                    'file_url' => $url,
                    'file_name' => $fileName
                ]
            ], 200);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar acta a PDF: ' . $e->getMessage()
            ], $code);
        }
    }
}
