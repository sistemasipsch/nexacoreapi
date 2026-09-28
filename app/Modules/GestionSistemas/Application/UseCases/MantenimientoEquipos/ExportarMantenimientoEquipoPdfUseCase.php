<?php

namespace App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos;

use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Exception;
use App\Models\PcMantenimiento;

class ExportarMantenimientoEquipoPdfUseCase
{
    public function __construct(
        protected ExcelToPdfConverterInterface $pdfConverter
    ) {}

    public function execute(int $id): string
    {
        $mantenimiento = PcMantenimiento::with([
            'equipo.sede',
            'equipo.area',
            'equipo.responsable.cargo',
            'equipo.caracteristicasTecnicas',
            'empresaResponsable',
            'creador:id,nombre_completo,firma_digital'
        ])->findOrFail($id);

        $templatePath = storage_path('app/templates/plantilla_mantenimiento_equipo.xlsx');
        
        if (!file_exists($templatePath)) {
            throw new Exception('No se encontró la plantilla de mantenimiento de equipo.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Datos del equipo y empresa
        $empresaNombre = optional($mantenimiento->empresaResponsable)->nombre ?? '';
        $equipoNombre = optional($mantenimiento->equipo)->nombre_equipo ?? '';
        $marca = optional($mantenimiento->equipo)->marca ?? '';
        $modelo = optional($mantenimiento->equipo)->modelo ?? '';
        $serial = optional($mantenimiento->equipo)->serial ?? '';
        
        $areaNombre = optional(optional($mantenimiento->equipo)->area)->nombre ?? '';
        $sedeNombre = optional(optional($mantenimiento->equipo)->sede)->nombre ?? '';

        // Limpiar cualquier residuo previo en columna A
        $sheet->setCellValue('A7', '');

        // Descombinar celdas previas de filas 6, 7 y 8 para organizar cada campo en su propia celda
        $sheet->unmergeCells('B6:AL6');
        $sheet->unmergeCells('B7:J7');
        $sheet->unmergeCells('K7:T7');
        $sheet->unmergeCells('U7:AC7');
        $sheet->unmergeCells('AD7:AL7');
        $sheet->unmergeCells('B8:AL8');

        // Fila 6: NOMBRE DE LA EMPRESA
        $sheet->mergeCells('B6:H6');
        $sheet->setCellValue('B6', 'NOMBRE DE LA EMPRESA:');
        $sheet->mergeCells('I6:AL6');
        $sheet->setCellValue('I6', $empresaNombre);

        // Fila 7: NOMBRE EQUIPO, MARCA, MODELO, SERIAL
        $sheet->mergeCells('B7:E7');
        $sheet->setCellValue('B7', 'NOMBRE EQUIPO:');
        $sheet->mergeCells('F7:J7');
        $sheet->setCellValue('F7', $equipoNombre);

        $sheet->mergeCells('K7:M7');
        $sheet->setCellValue('K7', 'MARCA:');
        $sheet->mergeCells('N7:T7');
        $sheet->setCellValue('N7', $marca);

        $sheet->mergeCells('U7:W7');
        $sheet->setCellValue('U7', 'MODELO:');
        $sheet->mergeCells('X7:AC7');
        $sheet->setCellValue('X7', $modelo);

        $sheet->mergeCells('AD7:AF7');
        $sheet->setCellValue('AD7', 'SERIAL:');
        $sheet->mergeCells('AG7:AL7');
        $sheet->setCellValue('AG7', $serial);

        // Fila 8: UBICACION
        $sheet->mergeCells('B8:E8');
        $sheet->setCellValue('B8', 'UBICACION:');
        $sheet->mergeCells('F8:AL8');
        $sheet->setCellValue('F8', trim($areaNombre . ' - ' . $sedeNombre, ' - '));

        // Bordes finos y alineación centrada para todos los campos de encabezado en filas 6, 7 y 8
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];
        $sheet->getStyle('B6:AL8')->applyFromArray($borderStyle);

        // Etiquetas en negrita
        $sheet->getStyle('B6')->getFont()->setBold(true);
        $sheet->getStyle('B7')->getFont()->setBold(true);
        $sheet->getStyle('K7')->getFont()->setBold(true);
        $sheet->getStyle('U7')->getFont()->setBold(true);
        $sheet->getStyle('AD7')->getFont()->setBold(true);
        $sheet->getStyle('B8')->getFont()->setBold(true);

        // Insertar datos de mantenimiento en fila 11
        if ($mantenimiento->fecha) {
            $fecha = Carbon::parse($mantenimiento->fecha);
            $sheet->setCellValue('B11', $fecha->format('Y'));
            $sheet->setCellValue('D11', $fecha->format('m'));
            $sheet->setCellValue('E11', $fecha->format('d'));
        }

        $sheet->setCellValue('F11', $mantenimiento->cpu ? 'X' : '');
        $sheet->setCellValue('G11', $mantenimiento->pantalla ? 'X' : '');
        $sheet->setCellValue('H11', $mantenimiento->teclado ? 'X' : '');
        $sheet->setCellValue('I11', $mantenimiento->mouse ? 'X' : '');
        $sheet->setCellValue('J11', $mantenimiento->unidad_cd ? 'X' : '');

        $sheet->setCellValue('K11', $mantenimiento->tipo_mantenimiento === 'preventivo' ? 'X' : '');
        $sheet->setCellValue('M11', $mantenimiento->tipo_mantenimiento === 'correctivo' ? 'X' : '');

        $sheet->setCellValue('O11', $mantenimiento->descripcion ?? '');

        $sheet->setCellValue('W11', $mantenimiento->repuesto ? 'X' : '');
        $sheet->setCellValue('X11', $mantenimiento->repuesto ? '' : 'X');

        $sheet->setCellValue('Y11', $mantenimiento->cantidad_repuesto ?? '');
        $sheet->setCellValue('Z11', $mantenimiento->costo_repuesto ?? '');
        $sheet->setCellValue('AB11', $mantenimiento->nombre_repuesto ?? '');

        // Alinear todos los campos de la fila 11 al centro (horizontal y vertical)
        $sheet->getStyle('B11:AL11')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B11:AL11')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('O11')->getAlignment()->setWrapText(true);

        // Resolver nombre del técnico
        $tecnicoNombre = $mantenimiento->responsable_mantenimiento;
        if (in_array((string)$tecnicoNombre, ['1665', '73'], true)) {
            $tecnicoNombre = 'ASHLY NAYLEA PARADA LEON';
        } elseif (in_array((string)$tecnicoNombre, ['1666', '7'], true)) {
            $tecnicoNombre = 'KEVIN DANIEL FLOREZ CONTRERAS';
        } elseif (in_array((string)$tecnicoNombre, ['1276', '65'], true)) {
            $tecnicoNombre = 'SERGIO ANDRES BARRERA RAMIREZ';
        } elseif (empty($tecnicoNombre) && $mantenimiento->creador) {
            $tecnicoNombre = $mantenimiento->creador->nombre_completo;
        }

        // Resolver firmas (incluyendo corrección de firmas cruzadas)
        $rawPersonal = $mantenimiento->getRawOriginal('firma_personal_cargo');
        $rawSistemas = $mantenimiento->getRawOriginal('firma_sistemas');

        // Si firma_sistemas está vacía pero firma_personal_cargo tiene firma (firmas cruzadas)
        if (empty($rawSistemas) && !empty($rawPersonal)) {
            $rawSistemas = $rawPersonal;
            $rawPersonal = null;
        }

        // Si el funcionario no tiene firma directa en el acta, traer de la tabla personal
        if (empty($rawPersonal) && $mantenimiento->equipo && $mantenimiento->equipo->responsable) {
            $rawPersonal = $mantenimiento->equipo->responsable->firma;
        }

        // Si el técnico no tiene firma en el acta, fallback al creador o personal
        if (empty($rawSistemas)) {
            if ($mantenimiento->creador && $mantenimiento->creador->firma_digital) {
                $rawSistemas = $mantenimiento->creador->getRawOriginal('firma_digital');
            } elseif ($tecnicoNombre) {
                $tecnicoPersonal = \App\Models\Personal::where('nombre', 'LIKE', '%' . $tecnicoNombre . '%')
                    ->whereNotNull('firma')
                    ->first();
                if ($tecnicoPersonal && $tecnicoPersonal->firma) {
                    $rawSistemas = $tecnicoPersonal->firma;
                }
            }
        }

        $this->insertarFirma($sheet, $rawPersonal, 'AG11');
        $this->insertarFirma($sheet, $rawSistemas, 'AJ11');

        // Configuración de página uniforme: Carta Horizontal, exactamente 1 página centrada con márgenes holgados
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER);
        $sheet->getPageSetup()->setPrintArea('B2:AL17');
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(1);
        $sheet->getPageSetup()->setHorizontalCentered(true);
        $sheet->getPageSetup()->setVerticalCentered(true);
        $sheet->getPageMargins()->setTop(0.6);
        $sheet->getPageMargins()->setBottom(0.6);
        $sheet->getPageMargins()->setLeft(0.7);
        $sheet->getPageMargins()->setRight(0.7);

        $filename = 'mantenimiento_equipo_' . $mantenimiento->id . '_' . time() . '.pdf';

        $tempExcelPath = tempnam(sys_get_temp_dir(), 'mantenimiento_excel_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempExcelPath);
        $spreadsheet->disconnectWorksheets();

        try {
            $pdfContent = $this->pdfConverter->convert($tempExcelPath);
            @unlink($tempExcelPath);

            $exportDir = storage_path('app/public/exports');
            if (!file_exists($exportDir)) {
                mkdir($exportDir, 0777, true);
            }

            $exportPath = $exportDir . '/' . $filename;
            file_put_contents($exportPath, $pdfContent);

            return $filename;
        } catch (Exception $e) {
            @unlink($tempExcelPath);
            throw $e;
        }
    }

    private function insertarFirma($sheet, $path, string $cell, int $mergedWidthPx = 114): void
    {
        if (empty($path)) {
            return;
        }

        $fullPath = null;
        $isTempFile = false;

        // Extraer ruta limpia si es una URL o ruta legacy
        $cleanPath = $path;
        if (preg_match('/storage\/(.+)$/', $path, $matches)) {
            $cleanPath = ltrim($matches[1], '/');
        } else {
            $cleanPath = ltrim(str_replace(['storage/', 'public/'], '', $path), '/');
        }

        if (str_starts_with($cleanPath, 'http://') || str_starts_with($cleanPath, 'https://')) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(4)->get($cleanPath);
                if ($response->successful()) {
                    $tempPath = tempnam(sys_get_temp_dir(), 'firma_url_') . '.png';
                    file_put_contents($tempPath, $response->body());
                    $fullPath = $tempPath;
                    $isTempFile = true;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Error descargando firma desde URL ($cleanPath): " . $e->getMessage());
            }
        } else {
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($cleanPath)) {
                $fullPath = storage_path('app/public/' . $cleanPath);
            } elseif (file_exists(public_path('storage/' . $cleanPath))) {
                $fullPath = public_path('storage/' . $cleanPath);
            } elseif (file_exists(storage_path('app/public/' . $cleanPath))) {
                $fullPath = storage_path('app/public/' . $cleanPath);
            } else {
                // Fallback de producción si estamos en local y la imagen no ha sido descargada
                try {
                    $remoteUrl = 'https://nexacoreapi.clinicalhouse.co/storage/' . $cleanPath;
                    $response = \Illuminate\Support\Facades\Http::timeout(4)->get($remoteUrl);
                    if ($response->successful()) {
                        $localDir = storage_path('app/public/' . dirname($cleanPath));
                        if (!file_exists($localDir)) {
                            @mkdir($localDir, 0777, true);
                        }
                        $targetPath = storage_path('app/public/' . $cleanPath);
                        file_put_contents($targetPath, $response->body());
                        $fullPath = $targetPath;
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("No se pudo obtener firma remota de respaldo ($cleanPath): " . $e->getMessage());
                }
            }
        }

        if ($fullPath && file_exists($fullPath)) {
            $src = @imagecreatefromstring(file_get_contents($fullPath));
            if ($src) {
                imagealphablending($src, false);
                imagesavealpha($src, true);

                // Recortar márgenes transparentes/vacíos si está soportado
                if (function_exists('imagecropauto')) {
                    $cropped = @imagecropauto($src, IMG_CROP_DEFAULT);
                    if ($cropped !== false) {
                        imagedestroy($src);
                        $src = $cropped;
                        imagealphablending($src, false);
                        imagesavealpha($src, true);
                    }
                }

                $origWidth = imagesx($src);
                $origHeight = imagesy($src);

                // Dimensiones óptimas para que flote en el centro sin tocar bordes
                $maxWidth = 85;
                $maxHeight = 50;
                $scale = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1.0);
                $scaledWidth = (int)round($origWidth * $scale);
                $scaledHeight = (int)round($origHeight * $scale);

                $tempFirmaPath = tempnam(sys_get_temp_dir(), 'f_clean_') . '.png';
                imagepng($src, $tempFirmaPath);
                imagedestroy($src);

                // Ancho de la celda es ~114-121px y alto es 105px
                $offsetX = max(0, (int)round(($mergedWidthPx - $scaledWidth) / 2));
                $offsetY = max(0, (int)round((105 - $scaledHeight) / 2));

                $drawing = new Drawing();
                $drawing->setName('Firma');
                $drawing->setDescription('Firma');
                $drawing->setPath($tempFirmaPath);
                $drawing->setCoordinates($cell);
                $drawing->setWidth($scaledWidth);
                $drawing->setHeight($scaledHeight);
                $drawing->setOffsetX($offsetX);
                $drawing->setOffsetY($offsetY);
                $drawing->setWorksheet($sheet);

                register_shutdown_function(function() use ($tempFirmaPath) {
                    @unlink($tempFirmaPath);
                });
            }

            if ($isTempFile) {
                register_shutdown_function(function() use ($fullPath) {
                    @unlink($fullPath);
                });
            }
        }
    }

    private function sanitize(string $string): string
    {
        $string = preg_replace('/[^A-Za-z0-9\-\s]/', '', $string);
        return trim(preg_replace('/\s+/', '_', $string));
    }
}
