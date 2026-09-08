<?php

namespace App\Exports;

use App\Models\CpEntregaActivosFijos;
use App\Models\Usuario;
use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CpEntregaActivosFijosExport
{
    public function __construct(
        protected ?ExcelToPdfConverterInterface $pdfConverter = null
    ) {}

    public function generate(int $id): StreamedResponse
    {
        [$spreadsheet, $entrega, $filenameBase] = $this->buildSpreadsheet($id);

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        });

        $filename = $filenameBase . '.xlsx';
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');
        $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');

        return $response;
    }

    public function generatePdf(int $id): StreamedResponse
    {
        [$spreadsheet, $entrega, $filenameBase] = $this->buildSpreadsheet($id);
        $filename = $filenameBase . '.pdf';

        $tempExcelPath = tempnam(sys_get_temp_dir(), 'activos_excel_') . '.xlsx';

        while ($spreadsheet->getSheetCount() > 1) {
            $spreadsheet->removeSheetByIndex(1);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempExcelPath);
        $spreadsheet->disconnectWorksheets();

        try {
            if ($this->pdfConverter) {
                $pdfContent = $this->pdfConverter->convert($tempExcelPath);
            } else {
                $pdfContent = $this->convertDirectlyToPdf($tempExcelPath);
            }
            @unlink($tempExcelPath);

            return new StreamedResponse(function () use ($pdfContent) {
                echo $pdfContent;
            }, 200, [
                'Content-Type'                  => 'application/pdf',
                'Content-Disposition'           => 'attachment; filename="' . $filename . '"',
                'Content-Length'                => strlen($pdfContent),
                'Cache-Control'                 => 'max-age=0',
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ]);
        } catch (Exception $e) {
            @unlink($tempExcelPath);
            throw $e;
        }
    }

    private function convertDirectlyToPdf(string $excelFilePath): string
    {
        $spreadsheet = IOFactory::load($excelFilePath);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestRow();
        $sheet->getPageSetup()->setPrintArea("A1:U{$highestRow}");
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        while ($spreadsheet->getSheetCount() > 1) {
            $spreadsheet->removeSheetByIndex(1);
        }

        $tempPdfPath = tempnam(sys_get_temp_dir(), 'activos_pdf_') . '.pdf';
        IOFactory::registerWriter('Pdf', \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf::class);
        $writer = IOFactory::createWriter($spreadsheet, 'Pdf');
        $writer->save($tempPdfPath);
        $spreadsheet->disconnectWorksheets();

        $content = file_get_contents($tempPdfPath);
        @unlink($tempPdfPath);

        return $content;
    }

    public function buildSpreadsheet(int $id): array
    {
        $entrega = CpEntregaActivosFijos::with([
            'personal.cargo',
            'sede',
            'procesoSolicitante',
            'coordinador',
            'items.inventario'
        ])->findOrFail($id);

        $templatePath = storage_path('app/templates/plantilla_entrega_activos_fijos.xlsx');
        if (!file_exists($templatePath)) {
            throw new Exception('No se encontró la plantilla de entrega de activos fijos.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Limpiar dos puntos residuales en celda M5 de la plantilla
        $sheet->setCellValue('M5', '');

        // 1. Datos de Encabezado
        if ($entrega->fecha_entrega) {
            $fecha = Carbon::parse($entrega->fecha_entrega);
            $sheet->setCellValue('B8', $fecha->format('d'));
            $sheet->setCellValue('C8', $fecha->format('m'));
            $sheet->setCellValue('D8', $fecha->format('Y'));
        }

        $cargo = $entrega->personal?->cargo?->nombre ?? (is_string($entrega->personal?->cargo) ? $entrega->personal->cargo : 'N/A');

        $sheet->setCellValue('H6', $entrega->personal?->nombre ?? 'N/A');
        $sheet->setCellValue('H7', $entrega->personal?->cedula ?? 'N/A');
        $sheet->setCellValue('H8', $cargo);

        $sheet->setCellValue('O6', $entrega->coordinador?->nombre ?? 'N/A');
        $sheet->setCellValue('O7', $entrega->procesoSolicitante?->nombre ?? 'N/A');
        $sheet->setCellValue('O8', $entrega->sede?->nombre ?? 'N/A');

        // Alineación y centrado de encabezados
        $sheet->getStyle('B8:D8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('H6:L8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('O6:T8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('H6:L8')->getFont()->setSize(10)->setBold(false);
        $sheet->getStyle('O6:T8')->getFont()->setSize(10)->setBold(false);

        // 2. Items Dinámicos
        $startRow = 14;
        $templateRows = 5;
        $items = $entrega->items;
        $totalItems = $items->count();
        $extraRows = max(0, $totalItems - $templateRows);

        if ($extraRows > 0) {
            $sheet->insertNewRowBefore($startRow + $templateRows, $extraRows);

            // Combinar celdas para las filas adicionales insertadas
            for ($r = $startRow + $templateRows; $r < $startRow + $totalItems; $r++) {
                $sheet->mergeCells("B{$r}:D{$r}");
                $sheet->mergeCells("E{$r}:F{$r}");
            }
        }

        $mapPrefix = ["EB" => "L", "MAQ" => "M", "ME" => "N", "EC" => "O", "MC" => "P", "IMC" => "P"];

        foreach ($items as $i => $item) {
            $row = $startRow + $i;
            $inv = $item->inventario;

            $sheet->getRowDimension($row)->setVisible(true);

            $sheet->setCellValue("B{$row}", $inv?->nombre ?? 'N/A');
            $sheet->setCellValue("E{$row}", $inv?->proveedor ?? 'N/A');
            $sheet->setCellValue("G{$row}", $inv?->num_factu ?? 'N/A');
            $sheet->setCellValue("H{$row}", $inv?->marca ?? 'N/A');
            $sheet->setCellValue("I{$row}", $inv?->modelo ?? 'N/A');
            $sheet->setCellValue("J{$row}", $inv?->serial ?? 'N/A');
            $sheet->setCellValue("K{$row}", $inv?->codigo ?? 'N/A');

            // Marca con 'X' según prefijo o grupo del activo
            $colTipo = null;
            if ($inv && $inv->codigo) {
                if (preg_match('/^([A-Z]+)/i', trim($inv->codigo), $m)) {
                    $pref = strtoupper($m[1]);
                    if (isset($mapPrefix[$pref])) {
                        $colTipo = $mapPrefix[$pref];
                    }
                }
            }
            if (!$colTipo && $inv && $inv->grupo) {
                $grp = strtoupper(trim($inv->grupo));
                if (isset($mapPrefix[$grp])) {
                    $colTipo = $mapPrefix[$grp];
                }
            }
            if (!$colTipo && $inv && $inv->grupo_activos) {
                $grpA = strtoupper(trim($inv->grupo_activos));
                if (isset($mapPrefix[$grpA])) {
                    $colTipo = $mapPrefix[$grpA];
                }
            }
            if ($colTipo) {
                $sheet->setCellValue($colTipo . $row, "X");
            }

            $hasAccesorio = $item->es_accesorio || ($inv && strtolower($inv->tiene_accesorio ?? '') === 'si');
            $sheet->setCellValue($hasAccesorio ? "R{$row}" : "S{$row}", "X");
            $sheet->setCellValue("Q{$row}", $inv?->estado ?? 'N/A');

            $descAcc = $item->accesorio_descripcion ?: ($inv?->descripcion_accesorio ?? '');
            $sheet->setCellValue("T{$row}", $descAcc ?: 'N/A');
            $sheet->setCellValue("U{$row}", $inv?->observaciones ?? 'N/A');

            // Centrado y espaciado de items
            $sheet->getStyle("B{$row}:U{$row}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);

            $sheet->getStyle("B{$row}:U{$row}")->getFont()->setBold(false)->setSize(9);
            $sheet->getRowDimension($row)->setRowHeight(28);
        }

        // Asegurar formato uniforme en las filas vacías de la plantilla si hay menos de 5 items
        for ($r = $startRow + $totalItems; $r < $startRow + $templateRows; $r++) {
            $sheet->getStyle("B{$r}:U{$r}")->getFont()->setBold(false)->setSize(9);
            $sheet->getRowDimension($r)->setRowHeight(28);
        }

        // 3. Fila de Firmas (desplazada dinámicamente según filas insertadas)
        $sigRow = 20 + $extraRows;
        $sheet->getRowDimension($sigRow)->setRowHeight(58);
        if ($extraRows > 0) {
            $sheet->getRowDimension(19 + $extraRows)->setRowHeight(8.25);
        }

        $nombreEntrega = $entrega->coordinador?->nombre ?? '';
        $nombreRecibe = $entrega->personal?->nombre ?? '';
        $cedulaRecibe = $entrega->personal?->cedula ? " - C.C. {$entrega->personal->cedula}" : '';

        $labelEntrega = "NOMBRE Y FIRMA DE QUIEN ENTREGA" . ($nombreEntrega ? "\n{$nombreEntrega}" : '');
        $labelRecibe = "NOMBRE Y FIRMA DE QUIEN RECIBE" . ($nombreRecibe ? "\n{$nombreRecibe}{$cedulaRecibe}" : '');

        $sheet->setCellValue("B" . ($sigRow + 1), $labelEntrega);
        $sheet->setCellValue("N" . ($sigRow + 1), $labelRecibe);
        $sheet->getStyle("B" . ($sigRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle("N" . ($sigRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($sigRow + 1)->setRowHeight(20);
        $sheet->getRowDimension($sigRow + 2)->setRowHeight(18);

        // Resolver firmas con fallbacks
        $firmaEntrega = $entrega->getRawOriginal('firma_quien_entrega') 
            ?? $entrega->coordinador?->getRawOriginal('firma') 
            ?? $entrega->coordinador?->firma;

        if (empty($firmaEntrega)) {
            $admin = Usuario::whereNotNull('firma_digital')->where('firma_digital', '!=', '')->first();
            if ($admin) {
                $firmaEntrega = $admin->getRawOriginal('firma_digital');
            }
        }

        $firmaRecibe = $entrega->getRawOriginal('firma_quien_recibe') 
            ?? $entrega->personal?->getRawOriginal('firma') 
            ?? $entrega->personal?->firma;

        // Insertar firmas en las celdas centrales de los rangos combinados (B..M -> H, N..U -> R)
        $this->insertFirma($sheet, $firmaEntrega, "H{$sigRow}");
        $this->insertFirma($sheet, $firmaRecibe, "R{$sigRow}");

        // 4. Configuración de Página y Área de Impresión
        $highestRow = $sheet->getHighestRow();
        $sheet->getPageSetup()->setPrintArea("A1:U{$highestRow}");
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        $sheet->getPageMargins()->setTop(0.3);
        $sheet->getPageMargins()->setBottom(0.3);
        $sheet->getPageMargins()->setLeft(0.25);
        $sheet->getPageMargins()->setRight(0.25);

        $responsableSanitized = preg_replace('/[^A-Za-z0-9_\-]/', '_', $entrega->personal?->nombre ?? 'PERSONAL');
        $filenameBase = "entrega_activos_{$entrega->id}_{$responsableSanitized}";

        return [$spreadsheet, $entrega, $filenameBase];
    }

    private function insertFirma($sheet, $path, string $cell): void
    {
        $realPath = $this->resolveImagePath($path);
        if (!$realPath || !file_exists($realPath)) {
            return;
        }

        try {
            $imageInfo = @getimagesize($realPath);
            if (!$imageInfo) {
                return;
            }

            $drawing = new Drawing();
            $drawing->setName('Firma');
            $drawing->setDescription('Firma');
            $drawing->setPath($realPath);
            $drawing->setCoordinates($cell);
            $drawing->setResizeProportional(true);
            $drawing->setHeight(46);
            $drawing->setOffsetX(0);
            $drawing->setOffsetY(4);
            $drawing->setWorksheet($sheet);
        } catch (\Throwable $e) {
            // Continuar sin interrumpir la exportación si la imagen falla
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
                        $tempPath = tempnam(sys_get_temp_dir(), 'sig_') . '.' . strtolower($type[1]);
                        file_put_contents($tempPath, $decoded);
                        return $tempPath;
                    }
                }
            } catch (\Throwable $e) {
                // Ignorar error de decodificación
            }
            return null;
        }

        // URLs o rutas relativas
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
        } elseif (file_exists($path)) {
            return $path;
        }

        return null;
    }
}
