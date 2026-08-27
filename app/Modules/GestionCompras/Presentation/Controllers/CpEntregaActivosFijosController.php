<?php

namespace App\Modules\GestionCompras\Presentation\Controllers;

use App\Http\Controllers\Controller;

use App\Models\CpEntregaActivosFijos;
use App\Models\CpEntregaActivosFijosItem;
use App\Services\PermissionService;
use App\Modules\GestionCompras\Application\UseCases\EntregaActivosFijos\CrearEntregaActivosFijosUseCase;
use App\Modules\GestionCompras\Application\UseCases\EntregaActivosFijos\ActualizarEntregaActivosFijosUseCase;
use App\Modules\GestionCompras\Application\UseCases\EntregaActivosFijos\EliminarEntregaActivosFijosUseCase;
use App\Modules\GestionCompras\Application\UseCases\EntregaActivosFijos\ObtenerCoordinadoresEntregaUseCase;
use App\Modules\GestionCompras\Application\UseCases\EntregaActivosFijos\ObtenerEntregasPorCoordinadorUseCase;
use App\Modules\GestionCompras\Application\UseCases\EntregaActivosFijos\TransferirEntregaUseCase;
use App\Modules\GestionCompras\Application\UseCases\EntregaActivosFijos\TransferirTodoEntregaUseCase;
use App\Exports\CpEntregaActivosFijosExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;
use OpenApi\Attributes as OA;

class CpEntregaActivosFijosController extends Controller
{
    protected $permissionService;


    protected $crearUseCase;
    protected $actualizarUseCase;
    protected $eliminarUseCase;
    protected $obtenerCoordinadoresUseCase;
    protected $obtenerEntregasCoordUseCase;
    protected $transferirUseCase;
    protected $transferirTodoUseCase;

    public function __construct(
        PermissionService $permissionService,
        CrearEntregaActivosFijosUseCase $crearUseCase,
        ActualizarEntregaActivosFijosUseCase $actualizarUseCase,
        EliminarEntregaActivosFijosUseCase $eliminarUseCase,
        ObtenerCoordinadoresEntregaUseCase $obtenerCoordinadoresUseCase,
        ObtenerEntregasPorCoordinadorUseCase $obtenerEntregasCoordUseCase,
        TransferirEntregaUseCase $transferirUseCase,
        TransferirTodoEntregaUseCase $transferirTodoUseCase
    )
    {
        $this->permissionService = $permissionService;
        $this->crearUseCase = $crearUseCase;
        $this->actualizarUseCase = $actualizarUseCase;
        $this->eliminarUseCase = $eliminarUseCase;
        $this->obtenerCoordinadoresUseCase = $obtenerCoordinadoresUseCase;
        $this->obtenerEntregasCoordUseCase = $obtenerEntregasCoordUseCase;
        $this->transferirUseCase = $transferirUseCase;
        $this->transferirTodoUseCase = $transferirTodoUseCase;
    }
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/api/gestion-compras/cp-entrega-activos-fijos',
        tags: ['Entrega de Activos Fijos'],
        summary: 'Listar entregas de activos fijos',
        description: 'Obtiene la lista de entregas de activos fijos con sus relaciones, con paginación y filtros opcionales.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Número de página', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Límite por página', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Término de búsqueda', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sede_id', in: 'query', required: false, description: 'ID de la sede a filtrar', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de entregas'),
            new OA\Response(response: 403, description: 'Prohibido')
        ]
    )]
    public function index(Request $request)
    {
        // $this->permissionService->authorize('cp_entrega_activos_fijos.listar');
        $limit = $request->input('limit', 30);
        $limit = min((int)$limit, 300);

        $query = CpEntregaActivosFijos::with([
            'personal',
            'sede',
            'procesoSolicitante',
            'coordinador',
            'items.inventario'
        ]);

        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('personal', function($qPersonal) use ($search) {
                    $qPersonal->where('nombre', 'like', "%{$search}%")
                              ->orWhere('cedula', 'like', "%{$search}%");
                })->orWhereHas('coordinador', function($qCoord) use ($search) {
                    $qCoord->where('nombre', 'like', "%{$search}%");
                })->orWhere('id', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('id', 'desc')->paginate($limit);
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/api/gestion-compras/cp-entrega-activos-fijos',
        tags: ['Entrega de Activos Fijos'],
        summary: 'Crear entrega de activos fijos',
        description: 'Crea una nueva entrega de activos fijos con sus items.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['personal_id', 'sede_id', 'proceso_solicitante', 'coordinador_id', 'fecha_entrega', 'items'],
                    properties: [
                        new OA\Property(property: 'personal_id', type: 'integer', description: 'ID del personal que recibe'),
                        new OA\Property(property: 'sede_id', type: 'integer', description: 'ID de la sede'),
                        new OA\Property(property: 'proceso_solicitante', type: 'integer', description: 'ID del proceso solicitante'),
                        new OA\Property(property: 'coordinador_id', type: 'integer', description: 'ID del coordinador'),
                        new OA\Property(property: 'fecha_entrega', type: 'string', format: 'date', description: 'Fecha de entrega'),
                        new OA\Property(property: 'use_stored_signature_entrega', type: 'boolean', description: 'Usar firma guardada del usuario que entrega'),
                        new OA\Property(property: 'use_stored_signature_recibe', type: 'boolean', description: 'Usar firma guardada del usuario que recibe'),
                        new OA\Property(property: 'firma_quien_entrega', type: 'string', format: 'binary', description: 'Firma de quien entrega'),
                        new OA\Property(property: 'firma_quien_recibe', type: 'string', format: 'binary', description: 'Firma de quien recibe'),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            description: 'Lista de items a entregar',
                            items: new OA\Items(
                                type: 'object',
                                required: ['item_id'],
                                properties: [
                                    new OA\Property(property: 'item_id', type: 'integer', description: 'ID del item de inventario'),
                                    new OA\Property(property: 'es_accesorio', type: 'boolean', description: 'Si es un accesorio'),
                                    new OA\Property(property: 'accesorio_descripcion', type: 'string', description: 'Descripción del accesorio')
                                ]
                            )
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Entrega creada exitosamente'),
            new OA\Response(response: 422, description: 'Error de validación'),
            new OA\Response(response: 500, description: 'Error del servidor')
        ]
    )]
    public function store(Request $request)
    {
        $this->permissionService->authorize('cp_entrega_activos_fijos.crear');

        $validated = $request->validate([
            'personal_id' => 'required|integer|exists:personal,id',
            'sede_id' => 'required|integer|exists:sedes,id',
            'proceso_solicitante' => 'required|integer|exists:dependencias_sedes,id',
            'coordinador_id' => 'required|integer|exists:personal,id',
            'fecha_entrega' => 'required|date',
            'use_stored_signature_entrega' => 'nullable|boolean',
            'use_stored_signature_recibe' => 'nullable|boolean',
            'firma_quien_entrega' => 'nullable|file|mimes:png,jpg|max:1024',
            'firma_quien_recibe' => 'nullable|file|mimes:png,jpg|max:1024',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:inventario,id',
            'items.*.es_accesorio' => 'nullable|boolean',
            'items.*.accesorio_descripcion' => 'nullable|string',
        ]);

        try {
            $entrega = $this->crearUseCase->execute(
                $validated,
                $request->file('firma_quien_entrega'),
                $request->file('firma_quien_recibe'),
                $request->boolean('use_stored_signature_entrega'),
                $request->boolean('use_stored_signature_recibe'),
                auth('api')->user()
            );

            return response()->json([
                'mensaje' => 'Entrega de activos fijos creada exitosamente',
                'objeto' => $entrega,
                'status' => 201
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'mensaje' => 'Error al crear la entrega: ' . $e->getMessage(),
                'objeto' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/api/gestion-compras/cp-entrega-activos-fijos/{id}',
        tags: ['Entrega de Activos Fijos'],
        summary: 'Obtener entrega específica',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Entrega encontrada'),
            new OA\Response(response: 404, description: 'Entrega no encontrada')
        ]
    )]
    public function show(string $id)
    {
        // $this->permissionService->authorize('cp_entrega_activos_fijos.listar');
        $entrega = CpEntregaActivosFijos::with([
            'personal',
            'sede',
            'procesoSolicitante',
            'coordinador',
            'items.inventario'
        ])->find($id);

        if (!$entrega) {
            return response()->json([
                'mensaje' => 'Entrega no encontrada',
                'objeto' => null,
                'status' => 404
            ], 404);
        }

        return response()->json([
            'mensaje' => 'Entrega encontrada',
            'objeto' => $entrega,
            'status' => 200
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
        path: '/api/gestion-compras/cp-entrega-activos-fijos/{id}',
        tags: ['Entrega de Activos Fijos'],
        summary: 'Actualizar entrega',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Entrega actualizada'),
            new OA\Response(response: 404, description: 'Entrega no encontrada')
        ]
    )]
    public function update(Request $request, string $id)
    {
        $this->permissionService->authorize('cp_entrega_activos_fijos.actualizar');

        $validated = $request->validate([
            'personal_id' => 'sometimes|integer|exists:personal,id',
            'sede_id' => 'sometimes|integer|exists:sedes,id',
            'proceso_solicitante' => 'sometimes|integer|exists:dependencias_sedes,id',
            'coordinador_id' => 'sometimes|integer|exists:personal,id',
            'fecha_entrega' => 'sometimes|date',
            'use_stored_signature_entrega' => 'nullable|boolean',
            'use_stored_signature_recibe' => 'nullable|boolean',
            'firma_quien_entrega' => 'nullable|file|mimes:png,jpg|max:1024',
            'firma_quien_recibe' => 'nullable|file|mimes:png,jpg|max:1024',
            'items' => 'sometimes|array|min:1',
            'items.*.item_id' => 'required_with:items|integer|exists:inventario,id',
            'items.*.es_accesorio' => 'nullable|boolean',
            'items.*.accesorio_descripcion' => 'nullable|string',
        ]);

        try {
            $entrega = $this->actualizarUseCase->execute(
                $id,
                $validated,
                $request->file('firma_quien_entrega'),
                $request->file('firma_quien_recibe'),
                $request->boolean('use_stored_signature_entrega'),
                $request->boolean('use_stored_signature_recibe'),
                auth('api')->user()
            );

            return response()->json([
                'mensaje' => 'Entrega actualizada exitosamente',
                'objeto' => $entrega,
                'status' => 200
            ]);
        } catch (Exception $e) {
            $status = $e->getMessage() === 'Entrega no encontrada' ? 404 : 500;
            return response()->json([
                'mensaje' => 'Error al actualizar la entrega: ' . $e->getMessage(),
                'objeto' => null,
                'status' => $status
            ], $status);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/api/gestion-compras/cp-entrega-activos-fijos/{id}',
        tags: ['Entrega de Activos Fijos'],
        summary: 'Eliminar entrega',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Entrega eliminada'),
            new OA\Response(response: 404, description: 'Entrega no encontrada')
        ]
    )]
    public function destroy(string $id)
    {
        $this->permissionService->authorize('cp_entrega_activos_fijos.eliminar');

        try {
            $this->eliminarUseCase->execute($id);

            return response()->json([
                'mensaje' => 'Entrega eliminada exitosamente',
                'objeto' => null,
                'status' => 200
            ]);
        } catch (Exception $e) {
            $status = $e->getMessage() === 'Entrega no encontrada' ? 404 : 500;
            return response()->json([
                'mensaje' => 'Error al eliminar la entrega: ' . $e->getMessage(),
                'objeto' => null,
                'status' => $status
            ], $status);
        }
    }

    /**
     * Exportar entrega a Excel.
     */
    #[OA\Get(
        path: '/api/gestion-compras/cp-entrega-activos-fijos/{id}/exportar-excel',
        tags: ['Entrega de Activos Fijos'],
        summary: 'Exportar entrega a Excel',
        description: 'Genera un archivo Excel basado en una plantilla para la entrega especificada.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo Excel generado'),
            new OA\Response(response: 404, description: 'Entrega no encontrada'),
            new OA\Response(response: 500, description: 'Error al generar el Excel')
        ]
    )]
    public function exportExcel($id)
    {
        $this->permissionService->authorize('cp_entrega_activos_fijos.listar');

        try {
            $export = new CpEntregaActivosFijosExport();
            return $export->generate((int)$id);
        } catch (Exception $e) {
            return response()->json([
                'mensaje' => 'Error al exportar a Excel: ' . $e->getMessage(),
                'objeto' => null,
                'status' => 500
            ], 500);
        }
    }

    public function coordinadores()
    {
        $this->permissionService->authorize('cp_entrega_activos_fijos.listar');
        return response()->json([
            'mensaje' => 'Coordinadores obtenidos exitosamente',
            'objeto' => $this->obtenerCoordinadoresUseCase->execute(),
            'status' => 200
        ]);
    }

    public function porCoordinador($id)
    {
        $this->permissionService->authorize('cp_entrega_activos_fijos.listar');
        return response()->json([
            'mensaje' => 'Entregas por coordinador obtenidas exitosamente',
            'objeto' => $this->obtenerEntregasCoordUseCase->execute($id),
            'status' => 200
        ]);
    }

    public function transferir(Request $request, $id)
    {
        $this->permissionService->authorize('cp_entrega_activos_fijos.transferir');

        $request->validate([
            'nuevo_coordinador_id' => 'required|exists:personal,id',
            'nuevo_personal_id' => 'nullable|exists:personal,id'
        ]);

        try {
            $nuevaActa = $this->transferirUseCase->execute(
                $id, 
                $request->nuevo_coordinador_id, 
                $request->nuevo_personal_id
            );

            return response()->json([
                'mensaje' => 'Acta transferida exitosamente',
                'objeto' => $nuevaActa,
                'status' => 201
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'mensaje' => 'Error al transferir el acta: ' . $e->getMessage(),
                'objeto' => null,
                'status' => 500
            ], 500);
        }
    }

    public function transferirTodo(Request $request)
    {
        $this->permissionService->authorize('cp_entrega_activos_fijos.transferir_actas_todo');

        $request->validate([
            'coordinador_viejo_id' => 'required|exists:personal,id',
            'nuevo_coordinador_id' => 'required|exists:personal,id'
        ]);

        try {
            $count = $this->transferirTodoUseCase->execute(
                $request->coordinador_viejo_id, 
                $request->nuevo_coordinador_id
            );

            return response()->json([
                'mensaje' => "Se han transferido $count actas exitosamente",
                'objeto' => $count,
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'mensaje' => 'Error al transferir las actas: ' . $e->getMessage(),
                'objeto' => null,
                'status' => 500
            ], 500);
        }
    }
}



