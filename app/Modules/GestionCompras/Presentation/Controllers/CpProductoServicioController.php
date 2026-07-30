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

    #[OA\Post(
        path: '/api/gestion-compras/cp-productos-servicios/sincronizar',
        tags: ['CpProductoServicios'],
        summary: 'Sincronizar producto/servicio desde sistema externo',
        description: 'Busca un producto/servicio por su código en el sistema externo (Gateway), validando que el código comience con un prefijo permitido (ACT, IMC-EC), y lo crea o actualiza en la base de datos local. Requiere permiso cp_producto_servicio.crear.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['codigo'],
                properties: [
                    new OA\Property(property: 'codigo', type: 'string', example: 'ACT-001', description: 'Código del producto/servicio a sincronizar')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Producto/servicio sincronizado', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 400, description: 'Error de validación de prefijo o no encontrado en sistema externo'),
            new OA\Response(response: 403, description: 'Prohibido')
        ]
    )]
    public function sincronizar(Request $request, \App\Modules\GestionCompras\Application\UseCases\ProductoServicio\SincronizarProductoServicioUseCase $sincronizarUseCase)
    {
        $this->permissionService->authorize('cp_producto_servicio.crear');

        $request->validate([
            'codigo' => 'required|string'
        ]);

        try {
            $item = $sincronizarUseCase->execute($request->input('codigo'));
            return ApiResponse::success($item, 'Producto/servicio sincronizado con éxito');
        } catch (\App\Exceptions\InvalidPrefixException $e) {
            return ApiResponse::error($e->getMessage(), 400, [
                'prefijos_permitidos' => $e->getAllowedPrefixes()
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}