<?php

namespace App\Modules\GestionSistemas\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionSistemas\Application\UseCases\EquiposComputo\ObtenerHojaVidaEquipoUseCase;
use App\Modules\GestionSistemas\Infrastructure\Repositories\PcEquipoRepository;
use App\Responses\ApiResponse;
use OpenApi\Attributes as OA;
use App\Modules\GestionSistemas\Application\UseCases\EquiposComputo\ExportarHojaVidaEquipoExcelUseCase;
use App\Modules\GestionSistemas\Application\UseCases\EquiposComputo\ExportarHojaVidaEquipoPdfUseCase;
use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;

class PcEquipoHojaVidaController extends Controller
{
    private ObtenerHojaVidaEquipoUseCase $obtenerHojaVidaUseCase;

    public function __construct()
    {
        $repository = new PcEquipoRepository();
        $this->obtenerHojaVidaUseCase = new ObtenerHojaVidaEquipoUseCase($repository);
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/pc-equipos/{id}/hoja-vida',
        tags: ['PcEquipos (DDD)'],
        summary: 'Obtener hoja de vida completa de un equipo',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Hoja de vida del equipo', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 404, description: 'No encontrado')
        ]
    )]
    public function show(int $id)
    {
        try {
            $data = $this->obtenerHojaVidaUseCase->execute($id);

            if (!$data) {
                return ApiResponse::error('Equipo no encontrado', 404);
            }

            return ApiResponse::success($data, 'Hoja de vida del equipo');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener hoja de vida: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/pc-equipos/{id}/hoja-vida/exportar-excel',
        tags: ['PcEquipos (DDD)'],
        summary: 'Exportar hoja de vida a Excel',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Archivo Excel generado', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 404, description: 'No encontrado')
        ]
    )]
    public function exportarExcel($id)
    {
        try {
            $useCase = new ExportarHojaVidaEquipoExcelUseCase();
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
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar hoja de vida a Excel: ' . $e->getMessage()
            ], $code);
        }
    }

    #[OA\Get(
        path: '/api/gestion-sistemas/pc-equipos/{id}/hoja-vida/exportar-pdf',
        tags: ['PcEquipos (DDD)'],
        summary: 'Exportar hoja de vida a PDF',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Archivo PDF generado', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 404, description: 'No encontrado')
        ]
    )]
    public function exportarPdf($id, ExcelToPdfConverterInterface $pdfConverter)
    {
        try {
            $useCase = new ExportarHojaVidaEquipoPdfUseCase($pdfConverter);
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
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar hoja de vida a PDF: ' . $e->getMessage()
            ], $code);
        }
    }
}
