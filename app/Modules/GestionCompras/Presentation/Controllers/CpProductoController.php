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
        description: 'Obtiene la lista de productos con paginación dinámica. Permite buscar por nombre o código del producto. El límite por defecto es 20 y el máximo es 200.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Buscar por nombre o código del producto', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Cantidad de registros por página (por defecto 20, máximo 200)', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Número de página', schema: new OA\Schema(type: 'integer', default: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de productos', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))
        ]
    )]
    public function index(Request $request)
    {
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', 20);
        $perPage = min(max($perPage, 1), 200);

        $items = $this->listarUseCase->execute($search, $perPage);
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

    #[OA\Post(
        path: '/api/gestion-compras/cp-productos/sincronizar',
        tags: ['CpProductos'],
        summary: 'Sincronizar producto desde sistema externo',
        description: 'Busca un producto por su código en el sistema externo (Gateway), validando que el código comience con un prefijo permitido (ACT, IMC-EC), y lo crea o actualiza en la base de datos local.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['codigo'],
                properties: [
                    new OA\Property(property: 'codigo', type: 'string', example: 'ACT-001', description: 'Código del producto a sincronizar')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Producto sincronizado', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 400, description: 'Error de validación de prefijo o no encontrado en sistema externo')
        ]
    )]
    public function sincronizar(Request $request, \App\Modules\GestionCompras\Application\UseCases\Producto\SincronizarProductoUseCase $sincronizarUseCase)
    {
        $this->permissionService->authorize('cp_producto.crear'); // Podemos usar el permiso de crear o uno especifico

        $request->validate([
            'codigo' => 'required|string'
        ]);

        try {
            $producto = $sincronizarUseCase->execute($request->input('codigo'));
            return ApiResponse::success($producto, 'Producto sincronizado con éxito');
        } catch (\App\Exceptions\InvalidPrefixException $e) {
            return ApiResponse::error($e->getMessage(), 400, [
                'prefijos_permitidos' => $e->getAllowedPrefixes()
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}