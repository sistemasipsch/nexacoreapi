<?php

namespace App\Modules\GestionCompras\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\ProgramarPedidoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\ListarPedidosProgramadosUseCase;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\ObtenerPedidoProgramadoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\ActualizarPedidoProgramadoUseCase;
use App\Modules\GestionCompras\Application\UseCases\Pedidos\EliminarPedidoProgramadoUseCase;
use App\Modules\GestionCompras\Application\DTOs\ProgramarPedidoDTO;
use App\Modules\GestionCompras\Application\DTOs\ActualizarPedidoProgramadoDTO;
use OpenApi\Attributes as OA;

class CpPedidoProgramadoController extends Controller
{
    private ProgramarPedidoUseCase $programarPedidoUseCase;
    private ListarPedidosProgramadosUseCase $listarPedidosProgramadosUseCase;
    private ObtenerPedidoProgramadoUseCase $obtenerPedidoProgramadoUseCase;
    private ActualizarPedidoProgramadoUseCase $actualizarPedidoProgramadoUseCase;
    private EliminarPedidoProgramadoUseCase $eliminarPedidoProgramadoUseCase;

    public function __construct(
        ProgramarPedidoUseCase $programarPedidoUseCase,
        ListarPedidosProgramadosUseCase $listarPedidosProgramadosUseCase,
        ObtenerPedidoProgramadoUseCase $obtenerPedidoProgramadoUseCase,
        ActualizarPedidoProgramadoUseCase $actualizarPedidoProgramadoUseCase,
        EliminarPedidoProgramadoUseCase $eliminarPedidoProgramadoUseCase
    ) {
        $this->programarPedidoUseCase = $programarPedidoUseCase;
        $this->listarPedidosProgramadosUseCase = $listarPedidosProgramadosUseCase;
        $this->obtenerPedidoProgramadoUseCase = $obtenerPedidoProgramadoUseCase;
        $this->actualizarPedidoProgramadoUseCase = $actualizarPedidoProgramadoUseCase;
        $this->eliminarPedidoProgramadoUseCase = $eliminarPedidoProgramadoUseCase;
    }

    #[OA\Post(
        path: '/api/gestion-compras/pedidos-programados',
        tags: ['Gestion Compras - Pedidos Programados'],
        summary: 'Programar un nuevo pedido',
        description: 'Permite dejar un pedido en sala de espera hasta la fecha programada. Requiere permiso pedido.programar. Puede enviar la firma en base64 o como archivo.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'datos_pedido', type: 'object', description: 'JSON completo con los datos del pedido a crear', example: ["items" => [["nombre" => "Laptop", "cantidad" => 1]], "proceso_solicitante" => 3]),
                    new OA\Property(property: 'fecha_programada', type: 'string', format: 'date-time', description: 'Fecha y hora en la que debe ejecutarse el pedido', example: '2027-01-01 10:30:00'),
                    new OA\Property(property: 'creado_por', type: 'integer', description: 'ID del usuario que realiza la programación', example: 1),
                    new OA\Property(property: 'firma_base64', type: 'string', description: 'String base64 de la firma (Opcional si se envía archivo)')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pedido programado exitosamente'),
            new OA\Response(response: 400, description: 'Error de validación')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        // Si datos_pedido viene como JSON string (por FormData), lo decodificamos a array
        if (is_string($request->input('datos_pedido'))) {
            $request->merge([
                'datos_pedido' => json_decode($request->input('datos_pedido'), true)
            ]);
        }

        $request->validate([
            'datos_pedido' => 'required|array',
            'fecha_programada' => 'required|date',
            'creado_por' => 'required|integer',
            'firma_base64' => 'nullable|string',
            'firma_file' => 'nullable|file|image|mimes:jpeg,png,jpg,svg|max:2048',
            'use_stored_signature' => 'nullable|boolean'
        ]);

        $tipoSolicitudId = $request->input('datos_pedido.tipo_solicitud');
        if ($tipoSolicitudId) {
            $tipoSolicitud = \App\Models\CpTipoSolicitud::find($tipoSolicitudId);
            $esPrioritario = $tipoSolicitud && stripos($tipoSolicitud->nombre, 'prioritari') !== false;
            if ($esPrioritario) {
                $user = auth('api')->user() ?? auth()->user() ?? \App\Models\Usuario::find($request->input('creado_por'));
                if (!$user || !app(\App\Services\PermissionService::class)->canCreatePriorityOrder($user)) {
                    return response()->json([
                        'error' => 'No tienes permisos para realizar pedidos prioritarios. Solo las personas con el permiso cp_pedido.realizar_pedido_prioritario habilitado pueden hacer pedidos prioritarios, el resto solo puede hacer pedidos recurrentes.'
                    ], 403);
                }
            }
        }

        $dto = new ProgramarPedidoDTO(
            $request->input('datos_pedido'),
            $request->input('fecha_programada'),
            $request->input('creado_por'),
            $request->input('firma_base64'),
            $request->file('firma_file'),
            filter_var($request->input('use_stored_signature', false), FILTER_VALIDATE_BOOLEAN)
        );

        $pedidoProgramado = $this->programarPedidoUseCase->execute($dto);

        return response()->json([
            'message' => 'Pedido programado exitosamente',
            'data' => $pedidoProgramado
        ], 201);
    }

    #[OA\Get(
        path: '/api/gestion-compras/pedidos-programados',
        tags: ['Gestion Compras - Pedidos Programados'],
        summary: 'Listar pedidos programados',
        description: 'Obtiene el listado de pedidos programados o ejecutados. Permite filtrar por estado (programado, ejecutado, cancelado, error) o por el usuario que lo creó.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'estado', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['programado', 'ejecutado', 'cancelado', 'error'])),
            new OA\Parameter(name: 'creado_por', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de pedidos programados'),
            new OA\Response(response: 403, description: 'Prohibido')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->only(['estado', 'creado_por']);
        
        $pedidos = $this->listarPedidosProgramadosUseCase->execute($filtros);

        return response()->json([
            'data' => $pedidos
        ]);
    }

    #[OA\Get(
        path: '/api/gestion-compras/pedidos-programados/{id}',
        tags: ['Gestion Compras - Pedidos Programados'],
        summary: 'Obtener un pedido programado',
        description: 'Obtiene los detalles de un pedido programado por su ID.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle del pedido'),
            new OA\Response(response: 404, description: 'Pedido no encontrado')
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $pedido = $this->obtenerPedidoProgramadoUseCase->execute($id);

        if (!$pedido) {
            return response()->json(['message' => 'Pedido no encontrado'], 404);
        }

        return response()->json([
            'data' => $pedido
        ]);
    }

    #[OA\Post(
        path: '/api/gestion-compras/pedidos-programados/{id}',
        tags: ['Gestion Compras - Pedidos Programados'],
        summary: 'Actualizar un pedido programado',
        description: 'Permite editar un pedido programado que aún no se ha ejecutado. Se usa POST simulando PUT para envío de archivos.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'datos_pedido', type: 'object', description: 'JSON completo con los nuevos datos'),
                    new OA\Property(property: 'fecha_programada', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'firma_base64', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pedido actualizado'),
            new OA\Response(response: 404, description: 'Pedido no encontrado')
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        if (is_string($request->input('datos_pedido'))) {
            $request->merge([
                'datos_pedido' => json_decode($request->input('datos_pedido'), true)
            ]);
        }

        $request->validate([
            'datos_pedido' => 'nullable|array',
            'fecha_programada' => 'nullable|date',
            'firma_base64' => 'nullable|string',
            'firma_file' => 'nullable|file|image|mimes:jpeg,png,jpg,svg|max:2048',
            'use_stored_signature' => 'nullable|boolean'
        ]);

        $tipoSolicitudId = $request->input('datos_pedido.tipo_solicitud');
        if ($tipoSolicitudId) {
            $tipoSolicitud = \App\Models\CpTipoSolicitud::find($tipoSolicitudId);
            $esPrioritario = $tipoSolicitud && stripos($tipoSolicitud->nombre, 'prioritari') !== false;
            if ($esPrioritario) {
                $user = auth('api')->user() ?? auth()->user();
                if (!$user || !app(\App\Services\PermissionService::class)->canCreatePriorityOrder($user)) {
                    return response()->json([
                        'error' => 'No tienes permisos para realizar pedidos prioritarios. Solo las personas con el permiso cp_pedido.realizar_pedido_prioritario habilitado pueden hacer pedidos prioritarios, el resto solo puede hacer pedidos recurrentes.'
                    ], 403);
                }
            }
        }

        $dto = new ActualizarPedidoProgramadoDTO(
            $id,
            $request->input('datos_pedido'),
            $request->input('fecha_programada'),
            $request->input('firma_base64'),
            $request->file('firma_file'),
            filter_var($request->input('use_stored_signature', false), FILTER_VALIDATE_BOOLEAN)
        );

        try {
            $this->actualizarPedidoProgramadoUseCase->execute($dto);
            return response()->json([
                'message' => 'Pedido programado actualizado exitosamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    #[OA\Delete(
        path: '/api/gestion-compras/pedidos-programados/{id}',
        tags: ['Gestion Compras - Pedidos Programados'],
        summary: 'Eliminar un pedido programado',
        description: 'Elimina un pedido programado.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pedido eliminado'),
            new OA\Response(response: 404, description: 'Pedido no encontrado')
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $this->eliminarPedidoProgramadoUseCase->execute($id);
        
        return response()->json([
            'message' => 'Pedido eliminado exitosamente'
        ]);
    }
}
