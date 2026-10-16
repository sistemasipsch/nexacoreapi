<?php

namespace App\Modules\GestionInfraestructura\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Responses\ApiResponse;
use App\Modules\GestionInfraestructura\Application\DTOs\Equipos\CrearInfraEquipoDTO;
use App\Modules\GestionInfraestructura\Application\DTOs\Equipos\ActualizarInfraEquipoDTO;
use App\Modules\GestionInfraestructura\Application\DTOs\Equipos\FiltroInfraEquiposDTO;
use App\Modules\GestionInfraestructura\Presentation\Requests\CrearInfraEquipoRequest;
use App\Modules\GestionInfraestructura\Presentation\Requests\ActualizarInfraEquipoRequest;
use App\Modules\GestionInfraestructura\Presentation\Requests\RegistrarTrasladoRequest;
use App\Modules\GestionInfraestructura\Application\UseCases\Equipos\ListarInfraEquiposUseCase;
use App\Modules\GestionInfraestructura\Application\UseCases\Equipos\CrearInfraEquipoUseCase;
use App\Modules\GestionInfraestructura\Application\UseCases\Equipos\ActualizarInfraEquipoUseCase;
use App\Modules\GestionInfraestructura\Application\UseCases\Equipos\ObtenerInfraEquipoUseCase;
use App\Modules\GestionInfraestructura\Application\UseCases\Equipos\EliminarInfraEquipoUseCase;
use App\Modules\GestionInfraestructura\Application\UseCases\Equipos\RegistrarTrasladoUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Equipos Infraestructura',
    description: 'Gestión de inventario de equipos de infraestructura (aires acondicionados y otros)'
)]
class InfraEquipoController extends Controller
{
    public function __construct(
        protected ListarInfraEquiposUseCase $listarUseCase,
        protected CrearInfraEquipoUseCase $crearUseCase,
        protected ActualizarInfraEquipoUseCase $actualizarUseCase,
        protected ObtenerInfraEquipoUseCase $obtenerUseCase,
        protected EliminarInfraEquipoUseCase $eliminarUseCase,
        protected RegistrarTrasladoUseCase $trasladoUseCase
    ) {}

    #[OA\Get(
        path: '/api/infraestructura/equipos',
        summary: 'Listar equipos de infraestructura con filtros y paginación',
        tags: ['Equipos Infraestructura'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', description: 'Texto a buscar (serial, activo fijo, nombre, marca)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sede_id', in: 'query', description: 'ID de la sede', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'area_id', in: 'query', description: 'ID del área', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tipo_equipo', in: 'query', description: 'Tipo (Minisplit, Cassette, Central, etc.)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'estado_equipo', in: 'query', description: 'Estado (nuevo, usado, bueno, regular, malo, de_baja)', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Cantidad por página', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de equipos obtenida exitosamente')
        ]
    )]
    public function index(Request $request)
    {
        $filtro = FiltroInfraEquiposDTO::fromRequest($request);
        $equipos = $this->listarUseCase->execute($filtro);
        return ApiResponse::success($equipos, 'Lista de equipos de infraestructura obtenida exitosamente');
    }

    #[OA\Post(
        path: '/api/infraestructura/equipos',
        summary: 'Registrar nuevo equipo de infraestructura',
        tags: ['Equipos Infraestructura'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\JsonContent(
                    required: ['nombre_equipo', 'tipo_equipo', 'sede_id'],
                    properties: [
                        new OA\Property(property: 'nombre_equipo', type: 'string', example: 'Aire Split Sala de Juntas'),
                        new OA\Property(property: 'tipo_equipo', type: 'string', example: 'Minisplit'),
                        new OA\Property(property: 'sede_id', type: 'integer', example: 1),
                        new OA\Property(property: 'forma_adquisicion', type: 'string', enum: ['compra directa', 'alquiler', 'donacion', 'comodato'], example: 'compra directa'),
                        new OA\Property(property: 'marca', type: 'string', example: 'LG'),
                        new OA\Property(property: 'modelo', type: 'string', example: 'Dual Inverter 18K'),
                        new OA\Property(property: 'serial', type: 'string', example: 'LG-SPLIT-98214'),
                        new OA\Property(property: 'activo_fijo', type: 'string', example: 'INF-AIR-001'),
                        new OA\Property(property: 'capacidad', type: 'string', example: '18000 BTU'),
                        new OA\Property(property: 'voltaje', type: 'string', example: '220V'),
                        new OA\Property(property: 'amperaje', type: 'string', example: '8.5A'),
                        new OA\Property(property: 'frecuencia', type: 'string', example: '60 Hz'),
                        new OA\Property(property: 'area_id', type: 'integer', example: 2),
                        new OA\Property(property: 'ubicacion_especifica', type: 'string', example: 'Piso 2, Sala de Juntas'),
                        new OA\Property(property: 'fecha_compra', type: 'string', format: 'date', example: '2025-06-15'),
                        new OA\Property(property: 'garantia_meses', type: 'integer', example: 12),
                        new OA\Property(property: 'fecha_instalacion', type: 'string', format: 'date', example: '2025-06-20'),
                        new OA\Property(property: 'vida_util_anos', type: 'integer', example: 10),
                        new OA\Property(property: 'frecuencia_mantenimiento', type: 'string', example: 'Trimestral'),
                        new OA\Property(property: 'estado_equipo', type: 'string', enum: ['nuevo', 'usado', 'bueno', 'regular', 'malo', 'de_baja'], example: 'nuevo'),
                        new OA\Property(property: 'descripcion_general', type: 'string', example: 'Equipo de climatización para reuniones.'),
                        new OA\Property(property: 'observaciones', type: 'string', example: 'Instalado con soporte antivibratorio.'),
                        new OA\Property(property: 'repuestos_principales', type: 'string', example: 'Filtros lavables, capacitor 35uF.'),
                        new OA\Property(property: 'recomendaciones', type: 'string', example: 'Limpieza quincenal de filtros.'),
                        new OA\Property(property: 'equipos_adicionales', type: 'string', example: 'Control remoto de pared, breaker 20A.')
                    ]
                ),
                new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(
                        required: ['nombre_equipo', 'tipo_equipo', 'sede_id'],
                        properties: [
                            new OA\Property(property: 'nombre_equipo', type: 'string', example: 'Aire Split Sala de Juntas'),
                            new OA\Property(property: 'tipo_equipo', type: 'string', example: 'Minisplit'),
                            new OA\Property(property: 'sede_id', type: 'integer', example: 1),
                            new OA\Property(property: 'forma_adquisicion', type: 'string', example: 'compra directa'),
                            new OA\Property(property: 'marca', type: 'string', example: 'LG'),
                            new OA\Property(property: 'modelo', type: 'string', example: 'Dual Inverter 18K'),
                            new OA\Property(property: 'serial', type: 'string', example: 'LG-SPLIT-98214'),
                            new OA\Property(property: 'activo_fijo', type: 'string', example: 'INF-AIR-001'),
                            new OA\Property(property: 'capacidad', type: 'string', example: '18000 BTU'),
                            new OA\Property(property: 'voltaje', type: 'string', example: '220V'),
                            new OA\Property(property: 'amperaje', type: 'string', example: '8.5A'),
                            new OA\Property(property: 'frecuencia', type: 'string', example: '60 Hz'),
                            new OA\Property(property: 'area_id', type: 'integer', example: 2),
                            new OA\Property(property: 'ubicacion_especifica', type: 'string', example: 'Piso 2, Sala de Juntas'),
                            new OA\Property(property: 'descripcion_general', type: 'string', example: 'Equipo de climatización para reuniones.'),
                            new OA\Property(property: 'observaciones', type: 'string', example: 'Instalado con soporte antivibratorio.'),
                            new OA\Property(property: 'repuestos_principales', type: 'string', example: 'Filtros lavables, capacitor 35uF.'),
                            new OA\Property(property: 'recomendaciones', type: 'string', example: 'Limpieza quincenal de filtros.'),
                            new OA\Property(property: 'equipos_adicionales', type: 'string', example: 'Control remoto de pared, breaker 20A.'),
                            new OA\Property(property: 'imagen', type: 'string', format: 'binary', description: 'Fotografía del equipo')
                        ]
                    )
                )
            ]
        ),
        responses: [
            new OA\Response(response: 201, description: 'Equipo de infraestructura creado exitosamente'),
            new OA\Response(response: 422, description: 'Error de validación')
        ]
    )]
    public function store(CrearInfraEquipoRequest $request)
    {
        $dto = CrearInfraEquipoDTO::fromRequest($request);
        $imagen = $request->file('imagen');
        $equipo = $this->crearUseCase->execute($dto, $imagen, auth()->id());

        return ApiResponse::success($equipo, 'Equipo de infraestructura registrado exitosamente', 201);
    }

    #[OA\Get(
        path: '/api/infraestructura/equipos/{id}',
        summary: 'Obtener detalle de un equipo de infraestructura',
        tags: ['Equipos Infraestructura'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del equipo de infraestructura', required: true, schema: new OA\Schema(type: 'integer', default: 4, example: 4))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle del equipo'),
            new OA\Response(response: 404, description: 'Equipo no encontrado')
        ]
    )]
    public function show(int $id)
    {
        $equipo = $this->obtenerUseCase->execute($id);
        return ApiResponse::success($equipo, 'Detalle del equipo obtenido exitosamente');
    }

    #[OA\Post(
        path: '/api/infraestructura/equipos/{id}',
        summary: 'Actualizar equipo de infraestructura (admite JSON y multipart con imagen)',
        tags: ['Equipos Infraestructura'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del equipo de infraestructura', required: true, schema: new OA\Schema(type: 'integer', default: 4, example: 4))
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: [
                new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'nombre_equipo', type: 'string', example: 'Aire Split Sala de Juntas Modificado'),
                        new OA\Property(property: 'tipo_equipo', type: 'string', example: 'Minisplit'),
                        new OA\Property(property: 'sede_id', type: 'integer', example: 1),
                        new OA\Property(property: 'forma_adquisicion', type: 'string', example: 'compra directa'),
                        new OA\Property(property: 'marca', type: 'string', example: 'LG'),
                        new OA\Property(property: 'modelo', type: 'string', example: 'Dual Inverter 18K'),
                        new OA\Property(property: 'serial', type: 'string', example: 'LG-SPLIT-98214'),
                        new OA\Property(property: 'activo_fijo', type: 'string', example: 'INF-AIR-001'),
                        new OA\Property(property: 'capacidad', type: 'string', example: '18000 BTU'),
                        new OA\Property(property: 'voltaje', type: 'string', example: '220V'),
                        new OA\Property(property: 'amperaje', type: 'string', example: '8.5A'),
                        new OA\Property(property: 'frecuencia', type: 'string', example: '60 Hz'),
                        new OA\Property(property: 'area_id', type: 'integer', example: 2),
                        new OA\Property(property: 'ubicacion_especifica', type: 'string', example: 'Piso 2, Sala de Juntas'),
                        new OA\Property(property: 'fecha_compra', type: 'string', format: 'date', example: '2025-06-15'),
                        new OA\Property(property: 'garantia_meses', type: 'integer', example: 12),
                        new OA\Property(property: 'fecha_instalacion', type: 'string', format: 'date', example: '2025-06-20'),
                        new OA\Property(property: 'vida_util_anos', type: 'integer', example: 10),
                        new OA\Property(property: 'frecuencia_mantenimiento', type: 'string', example: 'Trimestral'),
                        new OA\Property(property: 'estado_equipo', type: 'string', example: 'bueno'),
                        new OA\Property(property: 'descripcion_general', type: 'string', example: 'Equipo de climatización para reuniones.'),
                        new OA\Property(property: 'observaciones', type: 'string', example: 'Mantenimiento realizado.'),
                        new OA\Property(property: 'repuestos_principales', type: 'string', example: 'Filtros lavables, capacitor 35uF.'),
                        new OA\Property(property: 'recomendaciones', type: 'string', example: 'Limpieza quincenal de filtros.'),
                        new OA\Property(property: 'equipos_adicionales', type: 'string', example: 'Control remoto de pared, breaker 20A.')
                    ]
                ),
                new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(property: 'nombre_equipo', type: 'string', example: 'Aire Split Sala de Juntas'),
                            new OA\Property(property: 'tipo_equipo', type: 'string', example: 'Minisplit'),
                            new OA\Property(property: 'sede_id', type: 'integer', example: 1),
                            new OA\Property(property: 'marca', type: 'string', example: 'LG'),
                            new OA\Property(property: 'modelo', type: 'string', example: 'Dual Inverter 18K'),
                            new OA\Property(property: 'capacidad', type: 'string', example: '18000 BTU'),
                            new OA\Property(property: 'descripcion_general', type: 'string'),
                            new OA\Property(property: 'observaciones', type: 'string'),
                            new OA\Property(property: 'imagen', type: 'string', format: 'binary', description: 'Nueva fotografía')
                        ]
                    )
                )
            ]
        ),
        responses: [
            new OA\Response(response: 200, description: 'Equipo actualizado exitosamente'),
            new OA\Response(response: 404, description: 'Equipo no encontrado')
        ]
    )]
    public function update(int $id, ActualizarInfraEquipoRequest $request)
    {
        $dto = ActualizarInfraEquipoDTO::fromRequest($request);
        $nuevaImagen = $request->file('imagen');
        $equipo = $this->actualizarUseCase->execute($id, $dto, $nuevaImagen);

        return ApiResponse::success($equipo, 'Equipo de infraestructura actualizado exitosamente');
    }

    #[OA\Delete(
        path: '/api/infraestructura/equipos/{id}',
        summary: 'Eliminar equipo de infraestructura',
        tags: ['Equipos Infraestructura'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del equipo de infraestructura', required: true, schema: new OA\Schema(type: 'integer', default: 4, example: 4))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Equipo eliminado exitosamente'),
            new OA\Response(response: 404, description: 'Equipo no encontrado')
        ]
    )]
    public function destroy(int $id)
    {
        $this->eliminarUseCase->execute($id);
        return ApiResponse::success(null, 'Equipo de infraestructura eliminado exitosamente');
    }

    #[OA\Post(
        path: '/api/infraestructura/equipos/{id}/traslado',
        summary: 'Registrar traslado de sede / área de un equipo',
        tags: ['Equipos Infraestructura'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', description: 'ID del equipo de infraestructura', required: true, schema: new OA\Schema(type: 'integer', default: 4, example: 4))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['fecha_traslado', 'sede_id'],
                properties: [
                    new OA\Property(property: 'fecha_traslado', type: 'string', format: 'date', example: '2026-10-08'),
                    new OA\Property(property: 'sede_id', type: 'integer', example: 2),
                    new OA\Property(property: 'traslado_dependencia_id', type: 'integer', example: 1),
                    new OA\Property(property: 'traslado_area_id', type: 'integer', example: 3),
                    new OA\Property(property: 'ubicacion_especifica', type: 'string', example: 'Consultorio 103'),
                    new OA\Property(property: 'motivo_observacion', type: 'string', example: 'Reubicación por remodelación')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Traslado registrado exitosamente')
        ]
    )]
    public function registrarTraslado(int $id, RegistrarTrasladoRequest $request)
    {
        $equipo = $this->trasladoUseCase->execute($id, $request->validated());
        return ApiResponse::success($equipo, 'Traslado del equipo registrado exitosamente');
    }

    #[OA\Get(
        path: '/api/infraestructura/equipos/imagen/{filename}',
        summary: 'Obtener fotografía del equipo de infraestructura',
        tags: ['Equipos Infraestructura'],
        parameters: [
            new OA\Parameter(name: 'filename', in: 'path', description: 'Nombre del archivo', required: true, schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Imagen obtenida')
        ]
    )]
    public function servirImagen(string $filename)
    {
        $filename = basename($filename);
        $path = storage_path('app/public/infraEquipos/' . $filename);

        if (!file_exists($path)) {
            // Si la imagen no está en local (ej. migración/backup de BD), descargarla de producción y cachearla localmente
            $remoteUrl = "https://nexacoreapi.clinicalhouse.co/public/storage/infraEquipos/" . $filename;
            try {
                $response = Http::timeout(6)->get($remoteUrl);
                if ($response->successful()) {
                    @mkdir(dirname($path), 0755, true);
                    file_put_contents($path, $response->body());
                }
            } catch (\Throwable $e) {
                // Silencioso si no hay conexión o no existe en remoto
            }
        }

        if (file_exists($path)) {
            $mimeType = mime_content_type($path) ?: 'image/jpeg';
            return response()->file($path, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        abort(404, 'Imagen no encontrada');
    }
}
