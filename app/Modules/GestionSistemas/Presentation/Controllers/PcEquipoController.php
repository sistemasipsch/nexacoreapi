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
        // En cada llamada al listado, ejecutamos un auto-escaneo liviano en backend para recuperar fotos huérfanas
        try {
            $recoveryUseCase = new \App\Modules\GestionSistemas\Application\UseCases\EquiposComputo\AutoRecuperarImagenesEquiposUseCase();
            $recoveryUseCase->execute();
        } catch (\Throwable $e) {
            // Continuar sin interrumpir el listado
        }

        $useCase = new ListarPcEquiposUseCase();
        $equipos = $useCase->execute($request->get('q'), $request->get('sede_id'));
        return ApiResponse::success($equipos, 'Lista de equipos obtenida exitosamente');
    }

    public function autoRecuperarImagenes(Request $request)
    {
        try {
            $useCase = new \App\Modules\GestionSistemas\Application\UseCases\EquiposComputo\AutoRecuperarImagenesEquiposUseCase();
            $stats = $useCase->execute();
            return ApiResponse::success($stats, 'Recuperación de imágenes completada');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al recuperar imágenes: ' . $e->getMessage(), 500);
        }
    }

    public function servirImagen(string $filename)
    {
        $filename = basename($filename);
        $candidates = [
            storage_path('app/public/equipos/' . $filename),
            storage_path('app/public/pcEquipos/' . $filename),
            public_path('storage/equipos/' . $filename),
            public_path('storage/pcEquipos/' . $filename),
            public_path('equipos/' . $filename),
            base_path('../equipos/' . $filename),
            base_path('../../equipos/' . $filename),
            base_path('../../../equipos/' . $filename),
            '/home/u528159717/public_html/equipos/' . $filename,
            '/home/u528159717/public_html/formsistemas/equipos/' . $filename,
            '/home/u528159717/public_html/jundspro/equipos/' . $filename,
            '/home/u528159717/public_html/nexacore/equipos/' . $filename,
            '/home/u528159717/public_html/nexacoreapi/storage/app/public/equipos/' . $filename,
        ];

        $foundPath = null;

        foreach ($candidates as $filePath) {
            if (file_exists($filePath) && is_file($filePath)) {
                $foundPath = $filePath;
                break;
            }
        }

        // Si no se encuentra en la lista fija, realizar búsqueda recursiva en el servidor
        if (!$foundPath) {
            $searchBases = [
                '/home/u528159717/public_html',
                '/home/u528159717',
                base_path('../..'),
            ];

            foreach ($searchBases as $sb) {
                if (is_dir($sb)) {
                    $found = $this->findFileRecursive($sb, $filename, 0, 4);
                    if ($found) {
                        $foundPath = $found;
                        break;
                    }
                }
            }
        }

        if ($foundPath) {
            $standardPath = storage_path('app/public/equipos/' . $filename);
            if (!file_exists($standardPath)) {
                $dir = dirname($standardPath);
                if (!file_exists($dir)) @mkdir($dir, 0777, true);
                @copy($foundPath, $standardPath);
            }

            $mimeType = @mime_content_type($foundPath) ?: 'image/jpeg';
            return response()->file($foundPath, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=31536000'
            ]);
        }

        return response()->json(['message' => 'Imagen no encontrada en el servidor'], 404);
    }

    private function findFileRecursive(string $dir, string $filename, int $depth = 0, int $maxDepth = 4): ?string
    {
        if ($depth > $maxDepth || !is_dir($dir) || !is_readable($dir)) {
            return null;
        }

        $base = basename($dir);
        if (in_array($base, ['node_modules', 'vendor', '.git', 'cache'])) {
            return null;
        }

        $directCheck = $dir . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($directCheck) && is_file($directCheck)) {
            return $directCheck;
        }

        $items = @scandir($dir);
        if ($items === false) return null;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $res = $this->findFileRecursive($path, $filename, $depth + 1, $maxDepth);
                if ($res) return $res;
            }
        }

        return null;
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
            'ip_fija' => 'nullable|ipv4',
            'sede_id' => 'nullable|integer|exists:sedes,id',
            'area_id' => 'nullable|integer|exists:areas,id',
            'responsable_id' => 'nullable|integer|exists:personal,id',
            'estado' => 'nullable|string|max:255',
            'fecha_ingreso' => 'nullable|date',
            'imagen' => 'nullable|file|mimes:jpeg,png,jpg,webp,gif,svg|max:5120',
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
                $validated['imagen'] = $request->file('imagen');
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
            'ip_fija' => 'sometimes|nullable|ipv4',
            'sede_id' => 'nullable|integer|exists:sedes,id',
            'area_id' => 'nullable|integer|exists:areas,id',
            'responsable_id' => 'nullable|integer|exists:personal,id',
            'estado' => 'nullable|string|max:255',
            'fecha_ingreso' => 'nullable|date',
            'imagen' => 'nullable|file|mimes:jpeg,png,jpg,webp,gif,svg|max:5120',
            'eliminar_imagen' => 'nullable|boolean',
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
                $validated['imagen'] = $request->file('imagen');
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
