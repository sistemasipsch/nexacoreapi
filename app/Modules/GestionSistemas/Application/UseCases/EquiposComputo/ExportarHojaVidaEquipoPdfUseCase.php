<?php

namespace App\Modules\GestionSistemas\Application\UseCases\EquiposComputo;

use App\Models\PcEquipo;
use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Illuminate\Support\Facades\Storage;
use Exception;

class ExportarHojaVidaEquipoPdfUseCase
{
    public function __construct(
        protected ExcelToPdfConverterInterface $pdfConverter
    ) {}

    public function execute(int $id): string
    {
        $equipo = PcEquipo::with([
            'sede', 
            'area', 
            'responsable', 
            'caracteristicasTecnicas.monitorInventario', 
            'caracteristicasTecnicas.tecladoInventario', 
            'caracteristicasTecnicas.mouseInventario'
        ])->findOrFail($id);

        $templatePath = storage_path('app/templates/plantilla_hoja_vida_equipos.xlsx');
        if (!file_exists($templatePath)) {
            throw new Exception('No se encontró la plantilla de hoja de vida.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // 1. Datos básicos
        $sheet->setCellValue('B6', $sheet->getCell('B6')->getValue() . ' ' . ($equipo->nombre_equipo ?? ''));
        $sheet->setCellValue('H6', $sheet->getCell('H6')->getValue() . ' ' . ($equipo->numero_inventario ?? ''));
        $sheet->setCellValue('B7', $sheet->getCell('B7')->getValue() . ' ' . ($equipo->descripcion_general ?? ''));
        $sheet->setCellValue('B8', $sheet->getCell('B8')->getValue() . ' ' . ($equipo->marca ?? ''));
        $sheet->setCellValue('H8', $sheet->getCell('H8')->getValue() . ' ' . ($equipo->modelo ?? ''));
        $sheet->setCellValue('B9', $sheet->getCell('B9')->getValue() . ' ' . ($equipo->serial ?? ''));
        $sheet->setCellValue('H9', $sheet->getCell('H9')->getValue() . ' ' . optional($equipo->sede)->nombre);
        $sheet->setCellValue('B10', $sheet->getCell('B10')->getValue() . ' ' . ($equipo->tipo ?? ''));
        $sheet->setCellValue('H10', $sheet->getCell('H10')->getValue() . ' '); 
        $sheet->setCellValue('B11', $sheet->getCell('B11')->getValue() . ' ' . optional($equipo->area)->nombre);
        $sheet->setCellValue('H11', $sheet->getCell('H11')->getValue() . ' ' . ($equipo->estado ?? ''));
        $sheet->setCellValue('B12', $sheet->getCell('B12')->getValue() . ' ' . ($equipo->garantia_meses ? $equipo->garantia_meses . ' meses' : ''));
        $sheet->setCellValue('H12', $sheet->getCell('H12')->getValue() . ' ' . optional($equipo->responsable)->nombre);

        // 1.1 Ajustar y centrar el Logo de la IPS dentro de B2:D4 (Caja: 188px ancho x 104px alto)
        foreach ($sheet->getDrawingCollection() as $d) {
            if ($d->getCoordinates() === 'B2' || str_contains(strtolower($d->getName()), 'imagen 1')) {
                $d->setWidth(160);
                $d->setHeight(70);
                $d->setOffsetX(14);
                $d->setOffsetY(17);
            }
        }

        // 2. Imagen del Equipo (Caja K6:M12 -> 190px ancho x 203px alto)
        $realImagePath = $this->resolveImagePath($equipo->imagen_url);
        if ($realImagePath && file_exists($realImagePath)) {
            $drawing = new Drawing();
            $drawing->setName('Imagen Equipo');
            $drawing->setDescription('Imagen Equipo');
            $drawing->setPath($realImagePath);
            $drawing->setCoordinates('K6');

            // Calcular escalado proporcional máximo que llene el cuadro K6:M12
            $maxW = 176;
            $maxH = 190;
            $boxW = 190;
            $boxH = 203;

            $size = @getimagesize($realImagePath);
            if ($size && $size[0] > 0 && $size[1] > 0) {
                $ratio = min($maxW / $size[0], $maxH / $size[1]);
                $finalW = (int) round($size[0] * $ratio);
                $finalH = (int) round($size[1] * $ratio);
            } else {
                $finalW = $maxW;
                $finalH = $maxH;
            }

            $offsetX = (int) max(0, round(($boxW - $finalW) / 2));
            $offsetY = (int) max(0, round(($boxH - $finalH) / 2));

            $drawing->setWidth($finalW);
            $drawing->setHeight($finalH);
            $drawing->setOffsetX($offsetX);
            $drawing->setOffsetY($offsetY);
            $drawing->setWorksheet($sheet);
        }

        // 3. Características Técnicas
        $tec = $equipo->caracteristicasTecnicas;
        if ($tec) {
            $sheet->setCellValue('B14', $sheet->getCell('B14')->getValue() . ' ' . ($tec->procesador ?? ''));
            $sheet->setCellValue('E14', $sheet->getCell('E14')->getValue() . ' ' . trim(($tec->disco_duro ?? '') . ' ' . ($tec->capacidad_disco ?? '')));
            $sheet->setCellValue('H14', $sheet->getCell('H14')->getValue() . ' ' . ($tec->tarjeta_red ?? ''));
            
            $monitor = optional($tec->monitorInventario)->nombre ?? $tec->monitor ?? '';
            $sheet->setCellValue('K14', $sheet->getCell('K14')->getValue() . ' ' . $monitor);
            
            $sheet->setCellValue('B15', $sheet->getCell('B15')->getValue() . ' ' . ($equipo->ip_fija ?? ''));
            $sheet->setCellValue('E15', $sheet->getCell('E15')->getValue() . ' ' . ($tec->usb ?? ''));
            $sheet->setCellValue('H15', $sheet->getCell('H15')->getValue() . ' ' . ($tec->tarjeta_sonido ?? ''));
            
            $teclado = optional($tec->tecladoInventario)->nombre ?? $tec->teclado ?? '';
            $sheet->setCellValue('K15', $sheet->getCell('K15')->getValue() . ' ' . $teclado);
            
            $sheet->setCellValue('B16', $sheet->getCell('B16')->getValue() . ' ' . ($tec->velocidad_red ?? ''));
            $sheet->setCellValue('E16', $sheet->getCell('E16')->getValue() . ' ' . ($tec->unidad_cd ?? ''));
            $sheet->setCellValue('H16', $sheet->getCell('H16')->getValue() . ' ' . ($tec->parlantes ?? ''));
            
            $mouse = optional($tec->mouseInventario)->nombre ?? $tec->mouse ?? '';
            $sheet->setCellValue('K16', $sheet->getCell('K16')->getValue() . ' ' . $mouse);
            
            $sheet->setCellValue('B17', $sheet->getCell('B17')->getValue() . ' ' . ($tec->memoria_ram ?? ''));
            $sheet->setCellValue('E17', $sheet->getCell('E17')->getValue() . ' ' . ($tec->tarjeta_video ?? ''));
            $sheet->setCellValue('H17', $sheet->getCell('H17')->getValue() . ' ' . ($tec->drive ?? ''));
            $sheet->setCellValue('K17', $sheet->getCell('K17')->getValue() . ' ' . ($tec->internet ?? ''));
        } else {
            $sheet->setCellValue('B15', $sheet->getCell('B15')->getValue() . ' ' . ($equipo->ip_fija ?? ''));
        }

        // 4. Forma de Adquisición
        $forma = strtoupper(trim($equipo->forma_adquisicion ?? ''));
        if ($forma === 'COMPRA DIRECTA' || $forma === 'COMPRA') {
            $sheet->setCellValue('D19', 'X');
        } elseif ($forma === 'ALQUILER') {
            $sheet->setCellValue('G19', 'X');
        } elseif ($forma === 'DONACION') {
            $sheet->setCellValue('J19', 'X');
        } elseif ($forma === 'COMODATO') {
            $sheet->setCellValue('M19', 'X');
        }

        // 5. Textos largos
        $sheet->setCellValue('B20', $sheet->getCell('B20')->getValue() . " \n" . ($equipo->repuestos_principales ?? ''));
        $sheet->setCellValue('B22', $sheet->getCell('B22')->getValue() . " \n" . ($equipo->equipos_adicionales ?? ''));
        $sheet->setCellValue('B24', $sheet->getCell('B24')->getValue() . " \n" . ($equipo->recomendaciones ?? ''));
        $sheet->setCellValue('B26', $sheet->getCell('B26')->getValue() . " \n" . ($equipo->observaciones ?? ''));
        
        $fechaEntrega = $equipo->fecha_entrega ? $equipo->fecha_entrega->format('d/m/Y') : '';
        $sheet->setCellValue('B28', $sheet->getCell('B28')->getValue() . ' ' . $fechaEntrega);

        // Configuración de página para PDF
        $sheet->getPageMargins()->setTop(0.3);
        $sheet->getPageMargins()->setBottom(0.3);
        $sheet->getPageMargins()->setLeft(0.3);
        $sheet->getPageMargins()->setRight(0.3);

        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(1);

        // Remover otras hojas
        while ($spreadsheet->getSheetCount() > 1) {
            $activeIndex = $spreadsheet->getActiveSheetIndex();
            $indexToRemove = $activeIndex === 0 ? 1 : 0;
            $spreadsheet->removeSheetByIndex($indexToRemove);
        }

        // Preparar archivo temporal para enviar a LibreOffice
        $filename = 'hoja_vida_equipo_' . $equipo->id . '_' . time() . '.pdf';
        $tempExcelPath = tempnam(sys_get_temp_dir(), 'hv_excel_') . '.xlsx';
        
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

    private function resolveImagePath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        // Base64 Data URI
        if (str_starts_with($path, 'data:image')) {
            try {
                if (preg_match('/^data:image\/(\w+);base64,/', $path, $type)) {
                    $data = substr($path, strpos($path, ',') + 1);
                    $decoded = base64_decode($data);
                    if ($decoded !== false) {
                        $tempPath = tempnam(sys_get_temp_dir(), 'equipo_img_') . '.' . strtolower($type[1]);
                        file_put_contents($tempPath, $decoded);
                        return $tempPath;
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
            return null;
        }

        $cleanPath = $path;
        if (preg_match('#/storage/(.+)#', $cleanPath, $matches)) {
            $cleanPath = $matches[1];
        }
        $cleanPath = ltrim(str_replace(['public/', 'storage/', 'api/'], '', $cleanPath), '/');

        if (Storage::disk('public')->exists($cleanPath)) {
            return storage_path('app/public/' . $cleanPath);
        } elseif (file_exists(public_path('storage/' . $cleanPath))) {
            return public_path('storage/' . $cleanPath);
        } elseif (file_exists(storage_path('app/public/' . $cleanPath))) {
            return storage_path('app/public/' . $cleanPath);
        } elseif (file_exists(storage_path('app/' . $cleanPath))) {
            return storage_path('app/' . $cleanPath);
        }

        return null;
    }
}

