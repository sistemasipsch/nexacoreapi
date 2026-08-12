<?php

namespace App\Modules\GestionSistemas\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Responses\ApiResponse;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\Storage;

use App\Modules\GestionSistemas\Infrastructure\Repositories\PcEquipoRepository;
use App\Modules\GestionSistemas\Application\UseCases\EquiposComputo\ListarPcEquiposUseCase;
use App\Modules\GestionSistemas\Application\UseCases\EquiposComputo\CrearPcEquipoUseCase;
use App\Modules\GestionSistemas\Application\UseCases\EquiposComputo\ActualizarPcEquipoUseCase;
use App\Modules\GestionSistemas\Application\UseCases\EquiposComputo\EliminarPcEquipoUseCase;
use App\Modules\GestionSistemas\Application\UseCases\EquiposComputo\ObtenerPcEquipoUseCase;
use App\Modules\GestionSistemas\Application\UseCases\EquiposComputo\BuscarPcEquiposUseCase;

class PcEquipoController extends Controller
{
    private PcEquipoRepository $repository;
    private PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->repository = new PcEquipoRepository();
        $this->permissionService = $permissionService;
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/pc-equipos',
        tags: ['PcEquipos (DDD)'],
        summary: 'Listar equipos de PC',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de equipos obtenida exitosamente')
        ]
    )]
    public function index(Request $request)
    {
        $useCase = new ListarPcEquiposUseCase();
        $equipos = $useCase->execute($request->get('q'), $request->get('sede_id'));
        return ApiResponse::success($equipos, 'Lista de equipos obtenida exitosamente');
    }

    #[OA\Post(
        path: '/api/gestion-sistemas/pc-equipos',
        tags: ['PcEquipos (DDD)'],
        summary: 'Crear equipo de PC',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Creado exitosamente')
        ]
    )]
    public function store(Request $request)
    {
        $this->permissionService->authorize('pc_equipo.crear');
        
        $validated = $request->validate([
            'serial' => 'required|string|unique:pc_equipos,serial|max:255',
            'numero_inventario' => 'nullable|string|unique:pc_equipos,numero_inventario|max:255',
            'nombre_equipo' => 'nullable|string|max:255',
            'marca' => 'nullable|string|max:255',
            'modelo' => 'nullable|string|max:255',
            'tipo' => 'nullable|string|max:255',
            'propiedad' => 'nullable|in:empleado,empresa',
            'ip_fija' => 'required|ipv4',
            'sede_id' => 'nullable|integer|exists:sedes,id',
            'area_id' => 'nullable|integer|exists:areas,id',
            'responsable_id' => 'nullable|integer|exists:personal,id',
            'estado' => 'nullable|string|max:255',
            'fecha_ingreso' => 'nullable|date',
            'imagen' => 'nullable|image|max:5120',
            'fecha_entrega' => 'nullable|date',
            'descripcion_general' => 'nullable|string',
            'garantia_meses' => 'nullable|integer',
            'forma_adquisicion' => 'nullable|in:compra,alquiler,donacion,comodato',
            'observaciones' => 'nullable|string',
            'repuestos_principales' => 'nullable|string',
            'recomendaciones' => 'nullable|string',
            'equipos_adicionales' => 'nullable|string',
        ]);

        try {
            if (auth()->check()) {
                $validated['creado_por'] = auth()->id();
            } else {
                return ApiResponse::error('Usuario no autenticado', 401);
            }

            if ($request->hasFile('imagen')) {
                $path = $request->file('imagen')->store('pcEquipos', 'public');
                $validated['imagen_url'] = 'storage/' . $path;
            }

            $useCase = new CrearPcEquipoUseCase($this->repository);
            $item = $useCase->execute($validated);
            
            return ApiResponse::success($item, 'Equipo creado exitosamente', 201);
        } catch (\Exception $e) {
            \Log::error('Error creating PcEquipo: ' . $e->getMessage());
            return ApiResponse::error('Error al crear equipo: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/pc-equipos/{id}',
        tags: ['PcEquipos (DDD)'],
        summary: 'Obtener detalles de un equipo',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Detalle del equipo', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 404, description: 'No encontrado')
        ]
    )]
    public function show($id)
    {
        try {
            $useCase = new ObtenerPcEquipoUseCase($this->repository);
            $item = $useCase->execute($id);

            if (!$item) {
                return ApiResponse::error('Equipo no encontrado', 404);
            }

            return ApiResponse::success($item, 'Detalle del equipo');
        } catch (\Exception $e) {
            return ApiResponse::error('Error fetching equipe: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Put(
        path: '/api/gestion-sistemas/pc-equipos/{id}',
        tags: ['PcEquipos (DDD)'],
        summary: 'Actualizar equipo',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Equipo actualizado exitosamente', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 404, description: 'No encontrado')
        ]
    )]
    public function update(Request $request, $id)
    {
        $this->permissionService->authorize('pc_equipo.actualizar');
        
        $useCaseGet = new ObtenerPcEquipoUseCase($this->repository);
        $item = $useCaseGet->execute($id);
        
        if (!$item) {
            return ApiResponse::error('Equipo no encontrado', 404);
        }

        $validated = $request->validate([
            'serial' => 'sometimes|string|max:255|unique:pc_equipos,serial,' . $id,
            'numero_inventario' => 'nullable|string|max:255|unique:pc_equipos,numero_inventario,' . $id,
            'nombre_equipo' => 'nullable|string|max:255',
            'marca' => 'nullable|string|max:255',
            'modelo' => 'nullable|string|max:255',
            'tipo' => 'nullable|string|max:255',
            'propiedad' => 'nullable|in:empleado,empresa',
            'ip_fija' => 'required|ipv4',
            'sede_id' => 'nullable|integer|exists:sedes,id',
            'area_id' => 'nullable|integer|exists:areas,id',
            'responsable_id' => 'nullable|integer|exists:personal,id',
            'estado' => 'nullable|string|max:255',
            'fecha_ingreso' => 'nullable|date',
            'imagen' => 'nullable|image|max:5120',
            'fecha_entrega' => 'nullable|date',
            'descripcion_general' => 'nullable|string',
            'garantia_meses' => 'nullable|integer',
            'forma_adquisicion' => 'nullable|in:compra,alquiler,donacion,comodato',
            'observaciones' => 'nullable|string',
            'repuestos_principales' => 'nullable|string',
            'recomendaciones' => 'nullable|string',
            'equipos_adicionales' => 'nullable|string',
        ]);

        try {
            if ($request->hasFile('imagen')) {
                if ($item->imagen_url) {
                    $oldPath = str_replace('storage/', '', $item->imagen_url);
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }

                $path = $request->file('imagen')->store('pcEquipos', 'public');
                $validated['imagen_url'] = 'storage/' . $path;
            }

            $useCase = new ActualizarPcEquipoUseCase($this->repository);
            $updated = $useCase->execute($id, $validated);
            
            return ApiResponse::success($updated, 'Equipo actualizado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar equipo: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Delete(
        path: '/api/gestion-sistemas/pc-equipos/{id}',
        tags: ['PcEquipos (DDD)'],
        summary: 'Eliminar equipo',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Equipo eliminado exitosamente', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 404, description: 'No encontrado')
        ]
    )]
    public function destroy($id)
    {
        $this->permissionService->authorize('pc_equipo.eliminar');
        
        $useCase = new EliminarPcEquipoUseCase($this->repository);
        if ($useCase->execute($id)) {
            return ApiResponse::success(null, 'Equipo eliminado exitosamente');
        }

        return ApiResponse::error('Equipo no encontrado o no se pudo eliminar', 404);
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/pc-equipos/buscar',
        tags: ['PcEquipos (DDD)'],
        summary: 'Buscar equipos',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Resultados de búsqueda', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))
        ]
    )]
    public function buscar(Request $request)
    {
        $search = $request->get('q') ?? '';
        $useCase = new BuscarPcEquiposUseCase($this->repository);
        $equipos = $useCase->execute($search);
        
        return ApiResponse::success($equipos, 'Resultados de búsqueda');
    }
}
