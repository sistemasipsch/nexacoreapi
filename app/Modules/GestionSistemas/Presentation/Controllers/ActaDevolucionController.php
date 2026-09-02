<?php

namespace App\Modules\GestionSistemas\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionSistemas\Application\DTOs\CrearActaDevolucionDTO;
use App\Modules\GestionSistemas\Application\DTOs\ActualizarActaDevolucionDTO;
use App\Modules\GestionSistemas\Application\UseCases\ActasDevolucion\CrearActaDevolucionUseCase;
use App\Modules\GestionSistemas\Application\UseCases\ActasDevolucion\ActualizarActaDevolucionUseCase;
use App\Modules\GestionSistemas\Application\UseCases\ActasDevolucion\ObtenerActaDevolucionUseCase;
use App\Modules\GestionSistemas\Application\UseCases\ActasDevolucion\EliminarActaDevolucionUseCase;
use App\Modules\GestionSistemas\Infrastructure\Repositories\ActaDevolucionRepository;
use App\Models\PcDevuelto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Modules\GestionSistemas\Application\UseCases\ActasDevolucion\ExportarActaDevolucionExcelUseCase;
use App\Modules\GestionSistemas\Application\UseCases\ActasDevolucion\ExportarActaDevolucionPdfUseCase;
use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;
use OpenApi\Attributes as OA;

class ActaDevolucionController extends Controller
{
    private CrearActaDevolucionUseCase $crearUseCase;
    private ActualizarActaDevolucionUseCase $actualizarUseCase;
    private ObtenerActaDevolucionUseCase $obtenerUseCase;
    private EliminarActaDevolucionUseCase $eliminarUseCase;

    public function __construct()
    {
        $repository = new ActaDevolucionRepository();
        $this->crearUseCase = new CrearActaDevolucionUseCase($repository);
        $this->actualizarUseCase = new ActualizarActaDevolucionUseCase($repository);
        $this->obtenerUseCase = new ObtenerActaDevolucionUseCase($repository);
        $this->eliminarUseCase = new EliminarActaDevolucionUseCase($repository);
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/actas-devolucion',
        tags: ['Actas Devolucion'],
        summary: 'Listar actas de devolución',
        description: 'Obtiene todas las actas de devolución con su respectiva entrega.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de actas de devolución')
        ]
    )]
    public function index(): JsonResponse
    {
        // Para simplificar lecturas complejas con relaciones en DDD, es pragmático usar CQRS (o Eloquent directo para la lectura rápida)
        $devoluciones = PcDevuelto::with(['entrega.equipo', 'entrega.funcionario'])->orderBy('id', 'desc')->get();
        return response()->json($devoluciones);
    }

    #[OA\Post(
        path: '/api/gestion-sistemas/actas-devolucion',
        tags: ['Actas Devolucion'],
        summary: 'Crear acta de devolución',
        description: 'Crea una nueva acta de devolución y permite subir firmas como archivos.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['entrega_id', 'fecha_devolucion'],
                    properties: [
                        new OA\Property(property: 'entrega_id', type: 'integer', description: 'ID del acta de entrega'),
                        new OA\Property(property: 'fecha_devolucion', type: 'string', format: 'date', description: 'Fecha de devolución (YYYY-MM-DD)'),
                        new OA\Property(property: 'firma_entrega', type: 'string', format: 'binary', description: 'Archivo de firma de quien devuelve'),
                        new OA\Property(property: 'firma_recibe', type: 'string', format: 'binary', description: 'Archivo de firma de sistemas (quien recibe)'),
                        new OA\Property(property: 'observaciones', type: 'string', description: 'Observaciones sobre el estado del hardware')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Acta de devolución creada con éxito'),
            new OA\Response(response: 422, description: 'Error de validación')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'entrega_id' => 'required|integer|exists:pc_entregas,id',
            'fecha_devolucion' => 'required|date',
            'firma_entrega' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'firma_recibe' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'observaciones' => 'nullable|string',
        ]);

        $dto = new CrearActaDevolucionDTO(
            $request->input('entrega_id'),
            $request->input('fecha_devolucion'),
            $request->input('observaciones'),
            $request->file('firma_entrega'),
            $request->file('firma_recibe')
        );

        try {
            $acta = $this->crearUseCase->execute($dto);
            return response()->json([
                'success' => true,
                'message' => 'Acta de devolución creada con éxito.',
                'data' => [
                    'id' => $acta->getId(),
                    'entrega_id' => $acta->getEntregaId(),
                    'fecha_devolucion' => $acta->getFechaDevolucion(),
                    'firma_entrega' => $acta->getFirmaEntrega() ? url('storage/' . ltrim(str_replace(['storage/', 'public/'], '', $acta->getFirmaEntrega()), '/')) : null,
                    'firma_recibe' => $acta->getFirmaRecibe() ? url('storage/' . ltrim(str_replace(['storage/', 'public/'], '', $acta->getFirmaRecibe()), '/')) : null,
                    'observaciones' => $acta->getObservaciones()
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear acta: ' . $e->getMessage()
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/actas-devolucion/{id}',
        tags: ['Actas Devolucion'],
        summary: 'Obtener acta de devolución',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalles del acta')
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $acta = \App\Models\PcDevuelto::with([
            'entrega.equipo', 
            'entrega.funcionario.cargo',
            'entrega.perifericos.inventario'
        ])->find($id);

        if (!$acta) {
            return response()->json([
                'success' => false,
                'message' => 'Acta de devolución no encontrada.'
            ], 404);
        }

        $perifericos = [];
        if ($acta->entrega && $acta->entrega->perifericos) {
            $perifericos = $acta->entrega->perifericos->map(function($p) {
                return [
                    'id' => $p->id,
                    'inventario_id' => $p->inventario_id,
                    'cantidad' => $p->cantidad,
                    'observaciones' => $p->observaciones,
                    'inventario' => $p->inventario ? [
                        'codigo' => $p->inventario->codigo,
                        'nombre' => $p->inventario->nombre,
                        'marca' => $p->inventario->marca,
                        'modelo' => $p->inventario->modelo,
                        'serial' => $p->inventario->serial,
                    ] : null
                ];
            });
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $acta->id,
                'entrega_id' => $acta->entrega_id,
                'fecha_devolucion' => $acta->fecha_devolucion,
                'observaciones' => $acta->observaciones,
                'firma_entrega' => $acta->firma_entrega_url,
                'firma_recibe' => $acta->firma_recibe_url,
                'entrega' => $acta->entrega ? [
                    'id' => $acta->entrega->id,
                    'fecha_entrega' => $acta->entrega->fecha_entrega,
                    'estado' => $acta->entrega->estado,
                    'equipo' => $acta->entrega->equipo ? [
                        'serial' => $acta->entrega->equipo->serial,
                        'marca' => $acta->entrega->equipo->marca,
                        'modelo' => $acta->entrega->equipo->modelo,
                        'nombre_equipo' => $acta->entrega->equipo->nombre_equipo,
                    ] : null,
                    'funcionario' => $acta->entrega->funcionario ? [
                        'nombre' => $acta->entrega->funcionario->nombre,
                        'cedula' => $acta->entrega->funcionario->cedula,
                        'cargo' => $acta->entrega->funcionario->cargo ? [
                            'nombre' => $acta->entrega->funcionario->cargo->nombre
                        ] : null,
                    ] : null,
                    'perifericos' => $perifericos
                ] : null
            ]
        ]);
    }

    #[OA\Post(
        path: '/api/gestion-sistemas/actas-devolucion/{id}',
        tags: ['Actas Devolucion'],
        summary: 'Actualizar acta de devolución',
        description: 'Actualiza una acta de devolución existente.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: '_method', type: 'string', example: 'PUT'),
                        new OA\Property(property: 'entrega_id', type: 'integer', description: 'ID del acta de entrega'),
                        new OA\Property(property: 'fecha_devolucion', type: 'string', format: 'date', description: 'Fecha de devolución (YYYY-MM-DD)'),
                        new OA\Property(property: 'firma_entrega', type: 'string', format: 'binary', description: 'Archivo de firma de quien devuelve'),
                        new OA\Property(property: 'firma_recibe', type: 'string', format: 'binary', description: 'Archivo de firma de sistemas (quien recibe)'),
                        new OA\Property(property: 'observaciones', type: 'string', description: 'Observaciones sobre el estado del hardware')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Acta de devolución actualizada con éxito'),
            new OA\Response(response: 404, description: 'Acta no encontrada'),
            new OA\Response(response: 500, description: 'Error interno')
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'entrega_id' => 'nullable|integer|exists:pc_entregas,id',
            'fecha_devolucion' => 'nullable|date',
            'firma_entrega' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'firma_recibe' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'observaciones' => 'nullable|string',
        ]);

        $dto = new ActualizarActaDevolucionDTO(
            $id,
            $request->input('entrega_id') ? (int) $request->input('entrega_id') : null,
            $request->input('fecha_devolucion'),
            $request->input('observaciones'),
            $request->file('firma_entrega'),
            $request->file('firma_recibe')
        );

        try {
            $acta = $this->actualizarUseCase->execute($dto);
            return response()->json([
                'success' => true,
                'message' => 'Acta de devolución actualizada con éxito.',
                'data' => [
                    'id' => $acta->getId(),
                    'entrega_id' => $acta->getEntregaId(),
                    'fecha_devolucion' => $acta->getFechaDevolucion(),
                    'firma_entrega' => $acta->getFirmaEntrega() ? url('storage/' . ltrim(str_replace(['storage/', 'public/'], '', $acta->getFirmaEntrega()), '/')) : null,
                    'firma_recibe' => $acta->getFirmaRecibe() ? url('storage/' . ltrim(str_replace(['storage/', 'public/'], '', $acta->getFirmaRecibe()), '/')) : null,
                    'observaciones' => $acta->getObservaciones()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar acta: ' . $e->getMessage()
            ], $e->getMessage() === 'Acta de devolución no encontrada' ? 404 : 500);
        }
    }

    #[OA\Delete(
        path: '/api/gestion-sistemas/actas-devolucion/{id}',
        tags: ['Actas Devolucion'],
        summary: 'Eliminar acta de devolución',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Eliminada con éxito')
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->eliminarUseCase->execute($id);

        if (!$deleted) {
            return response()->json(['success' => false, 'message' => 'No encontrada o no se pudo eliminar'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Eliminada con éxito']);
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/actas-devolucion/{id}/exportar-excel',
        tags: ['Actas Devolucion'],
        summary: 'Exportar acta de devolución a Excel',
        description: 'Genera y descarga un archivo Excel con la información de la acta de devolución.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo Excel generado exitosamente'),
            new OA\Response(response: 404, description: 'Acta no encontrada'),
            new OA\Response(response: 500, description: 'Error interno')
        ]
    )]
    public function exportExcel(int $id): JsonResponse|BinaryFileResponse
    {
        try {
            $useCase = new ExportarActaDevolucionExcelUseCase();
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
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Acta de devolución no encontrada.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar acta a Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/actas-devolucion/{id}/exportar-pdf',
        tags: ['Actas Devolucion'],
        summary: 'Exportar acta de devolución a PDF',
        description: 'Genera y descarga un archivo PDF con la información de la acta de devolución.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo PDF generado exitosamente'),
            new OA\Response(response: 404, description: 'Acta no encontrada'),
            new OA\Response(response: 500, description: 'Error interno')
        ]
    )]
    public function exportPdf(int $id, ExcelToPdfConverterInterface $pdfConverter)
    {
        try {
            $useCase = new ExportarActaDevolucionPdfUseCase($pdfConverter);
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
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Acta de devolución no encontrada.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar acta a PDF: ' . $e->getMessage()
            ], 500);
        }
    }
}
