<?php

namespace App\Modules\GestionSistemas\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionSistemas\Application\DTOs\ImagenMensual\SubirImagenMensualDTO;
use App\Modules\GestionSistemas\Application\UseCases\ImagenMensual\EliminarImagenMensualUseCase;
use App\Modules\GestionSistemas\Application\UseCases\ImagenMensual\ObtenerImagenMensualUseCase;
use App\Modules\GestionSistemas\Application\UseCases\ImagenMensual\SubirImagenMensualUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use App\Services\PermissionService;

class ImagenMensualController extends Controller
{
    private SubirImagenMensualUseCase $subirUseCase;
    private ObtenerImagenMensualUseCase $obtenerUseCase;
    private EliminarImagenMensualUseCase $eliminarUseCase;
    private PermissionService $permissionService;

    public function __construct(
        SubirImagenMensualUseCase $subirUseCase,
        ObtenerImagenMensualUseCase $obtenerUseCase,
        EliminarImagenMensualUseCase $eliminarUseCase,
        PermissionService $permissionService
    ) {
        $this->subirUseCase = $subirUseCase;
        $this->obtenerUseCase = $obtenerUseCase;
        $this->eliminarUseCase = $eliminarUseCase;
        $this->permissionService = $permissionService;
    }

    #[OA\Post(
        path: '/api/imagen-mensual',
        summary: 'Subir imagen mensual',
        description: 'Sube y reemplaza la imagen mensual del sistema. Solo se permiten archivos PNG. Requiere permiso imagen_mensual.crud.',
        security: [['bearerAuth' => []]],
        tags: ['Imagen Mensual'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['imagen'],
                    properties: [
                        new OA\Property(property: 'imagen', type: 'string', format: 'binary', description: 'Archivo de imagen (PNG)')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Imagen subida correctamente', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 400, description: 'Error de validación'),
            new OA\Response(response: 403, description: 'Prohibido')
        ]
    )]
    public function subir(Request $request)
    {
        $this->permissionService->authorize('imagen_mensual.crud');

        $request->validate([
            'imagen' => 'required|file|mimes:png|max:5120', // Máximo 5MB
        ]);

        $dto = new SubirImagenMensualDTO(
            archivo: $request->file('imagen'),
            subidoPor: $request->user()->id
        );

        $entity = $this->subirUseCase->execute($dto);

        return response()->json([
            'success' => true,
            'message' => 'Imagen subida correctamente',
            'data' => $entity->toArray()
        ], 200);
    }

    #[OA\Get(
        path: '/api/imagen-mensual',
        summary: 'Descargar imagen mensual',
        description: 'Descarga la imagen mensual vigente. No requiere permisos especiales.',
        security: [['bearerAuth' => []]],
        tags: ['Imagen Mensual'],
        responses: [
            new OA\Response(
                response: 200, 
                description: 'Archivo de imagen',
                content: new OA\MediaType(mediaType: 'image/png')
            ),
            new OA\Response(response: 404, description: 'No existe imagen cargada')
        ]
    )]
    public function descargar()
    {
        $activeImage = $this->obtenerUseCase->execute();

        if (!$activeImage || !Storage::disk('public')->exists($activeImage->ruta)) {
            return response()->json(['message' => 'No existe imagen cargada'], 404);
        }

        return Storage::disk('public')->download($activeImage->ruta, $activeImage->nombreOriginal);
    }

    #[OA\Delete(
        path: '/api/imagen-mensual',
        summary: 'Eliminar imagen mensual',
        description: 'Elimina la imagen mensual del sistema. Requiere permiso imagen_mensual.crud.',
        security: [['bearerAuth' => []]],
        tags: ['Imagen Mensual'],
        responses: [
            new OA\Response(response: 200, description: 'Imagen eliminada correctamente', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 403, description: 'Prohibido')
        ]
    )]
    public function eliminar(Request $request)
    {
        $this->permissionService->authorize('imagen_mensual.crud');

        $this->eliminarUseCase->execute();

        return response()->json([
            'success' => true,
            'message' => 'Imagen eliminada correctamente'
        ], 200);
    }
}
