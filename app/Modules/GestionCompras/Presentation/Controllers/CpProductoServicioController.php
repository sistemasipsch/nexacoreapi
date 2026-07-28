<?php

namespace App\Modules\GestionCompras\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionCompras\Application\UseCases\ProductoServicio\ListarProductoServicioUseCase;
use App\Modules\GestionCompras\Application\UseCases\ProductoServicio\ListarTodosProductoServicioUseCase;
use App\Modules\GestionCompras\Application\UseCases\ProductoServicio\CrearProductoServicioUseCase;
use App\Modules\GestionCompras\Application\UseCases\ProductoServicio\ObtenerProductoServicioUseCase;
use App\Modules\GestionCompras\Application\UseCases\ProductoServicio\ActualizarProductoServicioUseCase;
use App\Modules\GestionCompras\Application\UseCases\ProductoServicio\EliminarProductoServicioUseCase;
use App\Services\PermissionService;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CpProductoServicioController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ListarProductoServicioUseCase $listarUseCase,
        protected CrearProductoServicioUseCase $crearUseCase,
        protected ObtenerProductoServicioUseCase $obtenerUseCase,
        protected ActualizarProductoServicioUseCase $actualizarUseCase,
        protected EliminarProductoServicioUseCase $eliminarUseCase,
        protected ListarTodosProductoServicioUseCase $listarTodosUseCase
    ) {}

    public function buscar(Request $request)
    {
        $termino = $request->input('termino') ?? $request->input('q');
        if (!$termino) {
            return ApiResponse::success([], 'Término no proporcionado', 200);
        }

        $resultados = \App\Models\CpProductoServicio::where('nombre', 'like', "%$termino%")
            ->orWhere('codigo_producto', 'like', "%$termino%")
            ->get();
            
        return ApiResponse::success($resultados, 'Resultados locales');
    }

    public function buscarExterno(Request $request, \App\Modules\Gateway\Application\UseCases\BuscarArticulosGatewayUseCase $buscarArticulosUseCase)
    {
        $termino = $request->input('termino') ?? $request->input('q');
        if (!$termino) {
            return ApiResponse::error('Término requerido', 400);
        }

        $articulos = $buscarArticulosUseCase->execute($termino);
        
        foreach ($articulos as $item) {
            \App\Models\CpProductoServicio::updateOrCreate(
                ['codigo_producto' => $item->codigo],
                ['nombre' => $item->nombre]
            );
        }

        $synced = \App\Models\CpProductoServicio::where('nombre', 'like', "%$termino%")
            ->orWhere('codigo_producto', 'like', "%$termino%")
            ->get();

        return ApiResponse::success($synced, 'Resultados externos sincronizados');
    }

    #[OA\Get(
        path: '/api/gestion-compras/cp-producto-servicios',
        tags: ['CpProductoServicios'],
        summary: 'Listar producto/servicio',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Éxito')]
    )]
    public function index()
    {
        $items = $this->listarUseCase->execute();
        return ApiResponse::success($items, 'Lista de producto/servicio');
    }

    #[OA\Get(
        path: '/api/gestion-compras/cp-productos-servicios/todos',
        tags: ['CpProductoServicios'],
        summary: 'Listar todos los productos y servicios',
        description: 'Obtiene la lista completa de productos y servicios sin paginación ni límite.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista completa de productos y servicios', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))
        ]
    )]
    public function all()
    {
        $items = $this->listarTodosUseCase->execute();
        return ApiResponse::success($items, 'Lista completa de productos y servicios');
    }

    #[OA\Post(
        path: '/api/gestion-compras/cp-producto-servicios',
        tags: ['CpProductoServicios'],
        summary: 'Crear producto/servicio',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Éxito')]
    )]
    public function store(Request $request)
    {
        $this->permissionService->authorize('cp_producto_servicio.crear');
        try {
            $item = $this->crearUseCase->execute($request->all());
            return ApiResponse::success($item, ucfirst('producto/servicio') . ' creado exitosamente', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al crear: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/gestion-compras/cp-producto-servicios/{id}',
        tags: ['CpProductoServicios'],
        summary: 'Obtener producto/servicio',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Éxito')]
    )]
    public function show($id)
    {
        $item = $this->obtenerUseCase->execute($id);
        if (!$item) {
            return ApiResponse::error(ucfirst('producto/servicio') . ' no encontrado', 404);
        }
        return ApiResponse::success($item, 'Detalle de producto/servicio');
    }

    #[OA\Put(
        path: '/api/gestion-compras/cp-producto-servicios/{id}',
        tags: ['CpProductoServicios'],
        summary: 'Actualizar producto/servicio',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Éxito')]
    )]
    public function update(Request $request, $id)
    {
        $this->permissionService->authorize('cp_producto_servicio.actualizar');
        try {
            $item = $this->actualizarUseCase->execute($id, $request->all());
            return ApiResponse::success($item, ucfirst('producto/servicio') . ' actualizado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Delete(
        path: '/api/gestion-compras/cp-producto-servicios/{id}',
        tags: ['CpProductoServicios'],
        summary: 'Eliminar producto/servicio',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Éxito')]
    )]
    public function destroy($id)
    {
        $this->permissionService->authorize('cp_producto_servicio.eliminar');
        try {
            $this->eliminarUseCase->execute($id);
            return ApiResponse::success(null, ucfirst('producto/servicio') . ' eliminado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al eliminar: ' . $e->getMessage(), 500);
        }
    }
}