<?php

namespace App\Modules\GestionCompras\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionCompras\Application\UseCases\Producto\ListarProductoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Producto\ListarTodosProductosUseCase;
use App\Modules\GestionCompras\Application\UseCases\Producto\CrearProductoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Producto\ObtenerProductoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Producto\ActualizarProductoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Producto\EliminarProductoUseCase;
use App\Services\PermissionService;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CpProductoController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ListarProductoUseCase $listarUseCase,
        protected CrearProductoUseCase $crearUseCase,
        protected ObtenerProductoUseCase $obtenerUseCase,
        protected ActualizarProductoUseCase $actualizarUseCase,
        protected EliminarProductoUseCase $eliminarUseCase,
        protected ListarTodosProductosUseCase $listarTodosUseCase
    ) {}

    #[OA\Get(
        path: '/api/gestion-compras/cp-productos',
        tags: ['CpProductos'],
        summary: 'Listar productos',
        description: 'Obtiene la lista de productos paginada (máximo 20). Permite buscar por nombre o código del producto.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Buscar por nombre o código del producto', schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de productos', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))
        ]
    )]
    public function index(Request $request)
    {
        $search = $request->query('search');
        $items = $this->listarUseCase->execute($search);
        return ApiResponse::success($items, 'Lista de productos');
    }

    #[OA\Get(
        path: '/api/gestion-compras/cp-productos/todos',
        tags: ['CpProductos'],
        summary: 'Listar todos los productos',
        description: 'Obtiene la lista completa de productos sin paginación ni límite.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista completa de productos', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))
        ]
    )]
    public function all()
    {
        $items = $this->listarTodosUseCase->execute();
        return ApiResponse::success($items, 'Lista completa de productos');
    }

    #[OA\Post(
        path: '/api/gestion-compras/cp-productos',
        tags: ['CpProductos'],
        summary: 'Crear producto',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 201, description: 'Producto creado exitosamente')]
    )]
    public function store(Request $request)
    {
        $this->permissionService->authorize('cp_producto.crear');
        try {
            $item = $this->crearUseCase->execute($request->all());
            return ApiResponse::success($item, ucfirst('producto') . ' creado exitosamente', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al crear: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/gestion-compras/cp-productos/{id}',
        tags: ['CpProductos'],
        summary: 'Obtener producto',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Detalle de producto')]
    )]
    public function show($id)
    {
        $item = $this->obtenerUseCase->execute($id);
        if (!$item) {
            return ApiResponse::error(ucfirst('producto') . ' no encontrado', 404);
        }
        return ApiResponse::success($item, 'Detalle de producto');
    }

    #[OA\Put(
        path: '/api/gestion-compras/cp-productos/{id}',
        tags: ['CpProductos'],
        summary: 'Actualizar producto',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Producto actualizado exitosamente')]
    )]
    public function update(Request $request, $id)
    {
        $this->permissionService->authorize('cp_producto.actualizar');
        try {
            $item = $this->actualizarUseCase->execute($id, $request->all());
            return ApiResponse::success($item, ucfirst('producto') . ' actualizado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Delete(
        path: '/api/gestion-compras/cp-productos/{id}',
        tags: ['CpProductos'],
        summary: 'Eliminar producto',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Producto eliminado exitosamente')]
    )]
    public function destroy($id)
    {
        $this->permissionService->authorize('cp_producto.eliminar');
        try {
            $this->eliminarUseCase->execute($id);
            return ApiResponse::success(null, ucfirst('producto') . ' eliminado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al eliminar: ' . $e->getMessage(), 500);
        }
    }
}