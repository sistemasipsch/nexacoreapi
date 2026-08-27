<?php

namespace App\Modules\GestionSistemas\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionSistemas\Application\DTOs\CrearActaEntregaDTO;
use App\Modules\GestionSistemas\Application\DTOs\PerifericoDTO;
use App\Modules\GestionSistemas\Application\UseCases\ActasEntrega\CrearActaEntregaUseCase;
use App\Modules\GestionSistemas\Application\UseCases\ActasEntrega\ObtenerActaEntregaUseCase;
use App\Modules\GestionSistemas\Application\UseCases\ActasEntrega\EliminarActaEntregaUseCase;
use App\Modules\GestionSistemas\Infrastructure\Repositories\ActaEntregaRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Modules\GestionSistemas\Application\UseCases\ActasEntrega\ExportarActaEntregaExcelUseCase;
use App\Modules\GestionSistemas\Application\UseCases\ActasEntrega\ExportarActaEntregaPdfUseCase;
use OpenApi\Attributes as OA;
use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;

class ActaEntregaController extends Controller
{
    private CrearActaEntregaUseCase $crearUseCase;
    private ObtenerActaEntregaUseCase $obtenerUseCase;
    private EliminarActaEntregaUseCase $eliminarUseCase;

    public function __construct()
    {
        // En un escenario ideal, esto se inyecta mediante el Service Container
        $repository = new ActaEntregaRepository();
        $this->crearUseCase = new CrearActaEntregaUseCase($repository);
        $this->obtenerUseCase = new ObtenerActaEntregaUseCase($repository);
        $this->eliminarUseCase = new EliminarActaEntregaUseCase($repository);
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/actas-entrega',
        tags: ['Actas Entrega'],
        summary: 'Listar actas de entrega',
        description: 'Obtiene todas las actas de entrega con opción de filtrar por sede.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'sede_id', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'ID de la sede para filtrar')
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de actas de entrega')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = \App\Models\PcEntrega::with([
            'equipo.sede',
            'equipo.area',
            'funcionario.cargo'
        ])->orderBy('id', 'desc');

        if ($request->filled('sede_id') && $request->input('sede_id') !== 'todas') {
            $sedeId = $request->input('sede_id');
            $query->whereHas('equipo', function ($q) use ($sedeId) {
                $q->where('sede_id', $sedeId);
            });
        }

        $entregas = $query->get();
        return response()->json($entregas);
    }

    #[OA\Post(
        path: '/api/gestion-sistemas/actas-entrega',
        tags: ['Actas Entrega'],
        summary: 'Crear acta de entrega',
        description: 'Crea una nueva acta de entrega junto con sus periféricos. Permite subir firmas en formato archivo.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['equipo_id', 'funcionario_id', 'fecha_entrega'],
                    properties: [
                        new OA\Property(property: 'equipo_id', type: 'integer', description: 'ID del equipo'),
                        new OA\Property(property: 'funcionario_id', type: 'integer', description: 'ID del funcionario'),
                        new OA\Property(property: 'fecha_entrega', type: 'string', format: 'date', description: 'Fecha de entrega (YYYY-MM-DD)'),
                        new OA\Property(property: 'firma_entrega', type: 'string', format: 'binary', description: 'Archivo de firma de quien entrega (jpeg, png, jpg, pdf)'),
                        new OA\Property(property: 'firma_recibe', type: 'string', format: 'binary', description: 'Archivo de firma de quien recibe (jpeg, png, jpg, pdf)'),
                        new OA\Property(property: 'perifericos', type: 'string', description: 'Arreglo JSON con los periféricos (ej. [{"inventario_id": 1, "cantidad": 2, "observaciones": "N/A"}])')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Acta de entrega creada con éxito'),
            new OA\Response(response: 422, description: 'Error de validación'),
            new OA\Response(response: 500, description: 'Error interno del servidor')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'equipo_id' => 'required|integer',
            'funcionario_id' => 'required|integer',
            'fecha_entrega' => 'required|date',
            'firma_entrega' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'firma_recibe' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'perifericos' => 'nullable|string', // Aceptamos string para JSON
        ]);

        $perifericosData = [];
        if ($request->has('perifericos') && !empty($request->input('perifericos'))) {
            $decoded = json_decode($request->input('perifericos'), true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    $perifericosData[] = new PerifericoDTO(
                        $item['inventario_id'],
                        $item['cantidad'] ?? 1,
                        $item['observaciones'] ?? null
                    );
                }
            }
        }

        $firmaGuardadaEntregaPath = null;
        if ($request->input('usar_firma_guardada_entrega') === 'true' && $request->user()) {
            $rawFirma = $request->user()->getAttributes()['firma_digital'] ?? $request->user()->firma_digital;
            $firmaGuardadaEntregaPath = $rawFirma ? ltrim(str_replace('storage/', '', $rawFirma), '/') : null;
        }

        $firmaGuardadaRecibePath = null;
        if ($request->input('usar_firma_guardada_recibe') === 'true' || (!$request->hasFile('firma_recibe') && empty($request->input('firma_recibe')))) {
            $personal = \App\Models\Personal::find($request->input('funcionario_id'));
            if ($personal && $personal->firma) {
                $firmaGuardadaRecibePath = ltrim(str_replace('storage/', '', $personal->firma), '/');
            }
        }

        $dto = new CrearActaEntregaDTO(
            $request->input('equipo_id'),
            $request->input('funcionario_id'),
            $request->input('fecha_entrega'),
            $request->file('firma_entrega'),
            $request->file('firma_recibe'),
            $firmaGuardadaEntregaPath,
            $firmaGuardadaRecibePath,
            $perifericosData
        );

        try {
            $acta = $this->crearUseCase->execute($dto);
            return response()->json([
                'success' => true,
                'message' => 'Acta de entrega creada con éxito.',
                'data' => [
                    'id' => $acta->getId(),
                    'equipo_id' => $acta->getEquipoId(),
                    'funcionario_id' => $acta->getFuncionarioId(),
                    'fecha_entrega' => $acta->getFechaEntrega()
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
        path: '/api/gestion-sistemas/actas-entrega/{id}',
        tags: ['Actas Entrega'],
        summary: 'Obtener acta de entrega',
        description: 'Obtiene los detalles de un acta de entrega por su ID, incluyendo sus periféricos y las URLs de las firmas.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID del acta de entrega')
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalles del acta de entrega'),
            new OA\Response(response: 404, description: 'Acta de entrega no encontrada')
        ]
    )]
    public function show(int $id): JsonResponse
    {
        // Pragmatic CQRS: Usamos Eloquent directo para lectura (Read Model)
        $acta = \App\Models\PcEntrega::with([
            'equipo.sede',
            'equipo.area',
            'funcionario.cargo',
            'perifericos.inventario'
        ])->find($id);

        if (!$acta) {
            return response()->json([
                'success' => false,
                'message' => 'Acta de entrega no encontrada.'
            ], 404);
        }

        $perifericos = $acta->perifericos->map(function($p) {
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

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $acta->id,
                'equipo_id' => $acta->equipo_id,
                'equipo' => $acta->equipo ? [
                    'id' => $acta->equipo->id,
                    'serial' => $acta->equipo->serial,
                    'marca' => $acta->equipo->marca,
                    'modelo' => $acta->equipo->modelo,
                    'nombre_equipo' => $acta->equipo->nombre_equipo,
                    'sede' => $acta->equipo->sede ? [
                        'id' => $acta->equipo->sede->id,
                        'nombre' => $acta->equipo->sede->nombre,
                    ] : null,
                    'area' => $acta->equipo->area ? [
                        'id' => $acta->equipo->area->id,
                        'nombre' => $acta->equipo->area->nombre,
                    ] : null,
                ] : null,
                'funcionario_id' => $acta->funcionario_id,
                'funcionario' => $acta->funcionario ? [
                    'nombre' => $acta->funcionario->nombre,
                    'cedula' => $acta->funcionario->cedula,
                    'cargo' => $acta->funcionario->cargo,
                ] : null,
                'fecha_entrega' => $acta->fecha_entrega,
                'firma_entrega' => $acta->firma_entrega_url,
                'firma_recibe' => $acta->firma_recibe_url,
                'estado' => $acta->estado,
                'devuelto' => $acta->devuelto,
                'perifericos' => $perifericos
            ]
        ]);
    }

    #[OA\Delete(
        path: '/api/gestion-sistemas/actas-entrega/{id}',
        tags: ['Actas Entrega'],
        summary: 'Eliminar acta de entrega',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID del acta de entrega')
        ],
        responses: [
            new OA\Response(response: 200, description: 'Acta de entrega eliminada'),
            new OA\Response(response: 404, description: 'Acta de entrega no encontrada')
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->eliminarUseCase->execute($id);

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Acta de entrega no encontrada o no se pudo eliminar.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Acta de entrega eliminada con éxito.'
        ]);
    }

    #[OA\Post(
        path: '/api/gestion-sistemas/actas-entrega/{id}',
        tags: ['Actas Entrega'],
        summary: 'Actualizar acta de entrega',
        description: 'Actualiza una acta de entrega existente. Requiere usar POST con el campo _method=PUT si se envían archivos multipart/form-data.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID del acta de entrega a actualizar')
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: '_method', type: 'string', example: 'PUT', description: 'Requerido para simular PUT en Laravel con FormData'),
                        new OA\Property(property: 'equipo_id', type: 'integer', description: 'ID del equipo'),
                        new OA\Property(property: 'funcionario_id', type: 'integer', description: 'ID del funcionario'),
                        new OA\Property(property: 'fecha_entrega', type: 'string', format: 'date', description: 'Fecha de entrega (YYYY-MM-DD)'),
                        new OA\Property(property: 'estado', type: 'string', description: 'Estado del acta (entregado, etc)'),
                        new OA\Property(property: 'firma_entrega', type: 'string', format: 'binary', description: 'Nuevo archivo de firma de quien entrega'),
                        new OA\Property(property: 'firma_recibe', type: 'string', format: 'binary', description: 'Nuevo archivo de firma de quien recibe'),
                        new OA\Property(property: 'perifericos', type: 'string', description: 'Arreglo JSON con los periféricos actualizados')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Acta actualizada exitosamente'),
            new OA\Response(response: 404, description: 'Acta no encontrada'),
            new OA\Response(response: 500, description: 'Error interno')
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'equipo_id' => 'nullable|integer',
            'funcionario_id' => 'nullable|integer',
            'fecha_entrega' => 'nullable|date',
            'estado' => 'nullable|string',
            'firma_entrega' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'firma_recibe' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'perifericos' => 'nullable|string',
        ]);

        $perifericosData = null;
        if ($request->has('perifericos') && !empty($request->input('perifericos'))) {
            $perifericosData = [];
            $decoded = json_decode($request->input('perifericos'), true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    $perifericosData[] = new PerifericoDTO(
                        $item['inventario_id'],
                        $item['cantidad'] ?? 1,
                        $item['observaciones'] ?? null
                    );
                }
            }
        }

        $firmaGuardadaEntregaPath = null;
        if ($request->input('usar_firma_guardada_entrega') === 'true' && $request->user()) {
            $rawFirma = $request->user()->getAttributes()['firma_digital'] ?? $request->user()->firma_digital;
            $firmaGuardadaEntregaPath = $rawFirma ? ltrim(str_replace('storage/', '', $rawFirma), '/') : null;
        }

        $firmaGuardadaRecibePath = null;
        if ($request->input('usar_firma_guardada_recibe') === 'true') {
            $funcionarioId = $request->input('funcionario_id');
            if ($funcionarioId) {
                $personal = \App\Models\Personal::find($funcionarioId);
                if ($personal && $personal->firma) {
                    $firmaGuardadaRecibePath = ltrim(str_replace('storage/', '', $personal->firma), '/');
                }
            }
        }

        $dto = new \App\Modules\GestionSistemas\Application\DTOs\ActualizarActaEntregaDTO(
            $id,
            $request->input('equipo_id'),
            $request->input('funcionario_id'),
            $request->input('fecha_entrega'),
            $request->file('firma_entrega'),
            $request->file('firma_recibe'),
            $firmaGuardadaEntregaPath,
            $firmaGuardadaRecibePath,
            $request->input('estado'),
            null, // devuelto
            $perifericosData
        );

        try {
            $useCase = new \App\Modules\GestionSistemas\Application\UseCases\ActasEntrega\ActualizarActaEntregaUseCase(new ActaEntregaRepository());
            $acta = $useCase->execute($dto);

            return response()->json([
                'success' => true,
                'message' => 'Acta de entrega actualizada con éxito.',
                'data' => [
                    'id' => $acta->getId(),
                    'equipo_id' => $acta->getEquipoId(),
                    'funcionario_id' => $acta->getFuncionarioId(),
                    'fecha_entrega' => $acta->getFechaEntrega()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar acta: ' . $e->getMessage()
            ], $e->getMessage() === 'Acta de entrega no encontrada' ? 404 : 500);
        }
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/actas-entrega/{id}/exportar-excel',
        tags: ['Actas Entrega'],
        summary: 'Exportar acta de entrega a Excel',
        description: 'Genera y descarga un archivo Excel con la información de la acta de entrega.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID del acta de entrega')
        ],
        responses: [
            new OA\Response(
                response: 200, 
                description: 'Archivo Excel generado exitosamente',
                content: new OA\MediaType(
                    mediaType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                )
            ),
            new OA\Response(response: 404, description: 'Acta de entrega no encontrada'),
            new OA\Response(response: 500, description: 'Error interno del servidor')
        ]
    )]
    public function exportExcel(int $id): JsonResponse|BinaryFileResponse
    {
        try {
            $useCase = new ExportarActaEntregaExcelUseCase();
            $fileName = $useCase->execute($id);
            $filePath = storage_path('app/public/exports/' . $fileName);
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
                'message' => 'Acta de entrega no encontrada.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar acta a Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/actas-entrega/{id}/exportar-pdf',
        tags: ['Actas Entrega'],
        summary: 'Exportar acta de entrega a PDF',
        description: 'Genera y descarga un archivo PDF con la información de la acta de entrega.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID del acta de entrega')
        ],
        responses: [
            new OA\Response(
                response: 200, 
                description: 'Archivo PDF descargado'
            ),
            new OA\Response(response: 404, description: 'Acta de entrega no encontrada'),
            new OA\Response(response: 500, description: 'Error interno del servidor')
        ]
    )]
    public function exportPdf(int $id, ExcelToPdfConverterInterface $pdfConverter)
    {
        try {
            $useCase = new ExportarActaEntregaPdfUseCase($pdfConverter);
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
                'message' => 'Acta de entrega no encontrada.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar acta a PDF: ' . $e->getMessage()
            ], 500);
        }
    }
}
