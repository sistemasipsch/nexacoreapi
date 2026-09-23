<?php

namespace App\Exports;

use App\Models\Inventario;
use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CpInventarioExport
{
    public function __construct(
        protected ?ExcelToPdfConverterInterface $pdfConverter = null
    ) {}

    /**
     * Generar archivo Excel para el inventario.
     */
    public function generateExcel($items, ?string $sedeNombre = null, ?string $extraInfo = null, bool $returnUrl = false): StreamedResponse|array
    {
        $spreadsheet = $this->buildExcelSpreadsheet($items, $sedeNombre, $extraInfo);

        $safeSede = $sedeNombre ? Str::slug($sedeNombre, '_') : 'TODAS_LAS_SEDES';
        $filename = 'inventario_' . $safeSede . '_' . Carbon::now('America/Bogota')->format('Y_m_d_His') . '.xlsx';

        if ($returnUrl) {
            $exportDir = storage_path('app/public/exports');
            if (!file_exists($exportDir)) {
                mkdir($exportDir, 0777, true);
            }
            $filePath = $exportDir . '/' . $filename;
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);
            $spreadsheet->disconnectWorksheets();

            return [
                'file_url'  => asset('storage/exports/' . $filename),
                'file_name' => $filename,
            ];
        }

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');
        $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');

        return $response;
    }

    /**
     * Generar archivo PDF para el inventario.
     */
    public function generatePdf($items, ?string $sedeNombre = null, ?string $extraInfo = null, bool $returnUrl = false): StreamedResponse|array
    {
        [$spreadsheet, $highestRow] = $this->buildPdfSpreadsheet($items, $sedeNombre, $extraInfo);

        $safeSede = $sedeNombre ? Str::slug($sedeNombre, '_') : 'TODAS_LAS_SEDES';
        $filename = 'inventario_' . $safeSede . '_' . Carbon::now('America/Bogota')->format('Y_m_d_His') . '.pdf';

        $tempExcelPath = tempnam(sys_get_temp_dir(), 'inv_pdf_excel_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempExcelPath);
        $spreadsheet->disconnectWorksheets();

        try {
            if ($this->pdfConverter) {
                try {
                    $pdfContent = $this->pdfConverter->convert($tempExcelPath);
                } catch (\Throwable $convEx) {
                    \Illuminate\Support\Facades\Log::warning('Fallo convertidor principal, usando local mPDF: ' . $convEx->getMessage());
                    $pdfContent = $this->convertDirectlyToPdf($tempExcelPath, $highestRow);
                }
            } else {
                $pdfContent = $this->convertDirectlyToPdf($tempExcelPath, $highestRow);
            }
            @unlink($tempExcelPath);

            if ($returnUrl) {
                $exportDir = storage_path('app/public/exports');
                if (!file_exists($exportDir)) {
                    mkdir($exportDir, 0777, true);
                }
                $exportPath = $exportDir . '/' . $filename;
                file_put_contents($exportPath, $pdfContent);

                return [
                    'file_url'  => asset('storage/exports/' . $filename),
                    'file_name' => $filename,
                ];
            }

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

    /**
     * Construcción de hoja de cálculo completa para Excel.
     */
    private function buildExcelSpreadsheet($items, ?string $sedeNombre = null, ?string $extraInfo = null): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventario');

        // 1. Título General (Fila 2)
        $sheet->mergeCells('A2:R2');
        $sheet->setCellValue('A2', 'NEXA - GESTIÓN DE COMPRAS: REPORTE GENERAL DE INVENTARIO');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14)->setName('Arial')->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('312E81'); // Indigo 900
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(2)->setRowHeight(38);

        // 2. Información del Reporte y Filtros (Fila 3)
        $sedeLabel = $sedeNombre ? mb_strtoupper($sedeNombre, 'UTF-8') : 'TODAS LAS SEDES';
        $subtitulo = "SEDE: {$sedeLabel}   |   GENERADO EL: " . Carbon::now('America/Bogota')->format('d/m/Y h:i A') . "   |   TOTAL ÍTEMS: " . count($items);
        if (!empty($extraInfo)) {
            $subtitulo .= "   |   FILTROS: {$extraInfo}";
        }

        $sheet->mergeCells('A3:R3');
        $sheet->setCellValue('A3', $subtitulo);
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(9.5)->setName('Arial')->getColor()->setRGB('3730A3'); // Indigo 700
        $sheet->getStyle('A3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2FF'); // Indigo 50
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(3)->setRowHeight(26);

        // Fila 4: Separador
        $sheet->getRowDimension(4)->setRowHeight(8);

        // 3. Encabezados de Columnas (Fila 5)
        $headers = [
            'A' => ['title' => '#', 'width' => 6],
            'B' => ['title' => 'Código', 'width' => 16],
            'C' => ['title' => 'Nombre del Activo', 'width' => 35],
            'D' => ['title' => 'Marca', 'width' => 18],
            'E' => ['title' => 'Modelo', 'width' => 20],
            'F' => ['title' => 'Serial', 'width' => 20],
            'G' => ['title' => 'Sede', 'width' => 26],
            'H' => ['title' => 'Dependencia / Proceso', 'width' => 26],
            'I' => ['title' => 'Ubicación', 'width' => 20],
            'J' => ['title' => 'Responsable', 'width' => 26],
            'K' => ['title' => 'Coordinador', 'width' => 26],
            'L' => ['title' => 'Estado', 'width' => 15],
            'M' => ['title' => 'Fecha Compra', 'width' => 15],
            'N' => ['title' => 'Valor Compra', 'width' => 18],
            'O' => ['title' => 'No. Factura', 'width' => 16],
            'P' => ['title' => 'Proveedor', 'width' => 26],
            'Q' => ['title' => 'Código Barras', 'width' => 18],
            'R' => ['title' => 'Observaciones', 'width' => 35],
        ];

        foreach ($headers as $col => $cfg) {
            $sheet->setCellValue($col . '5', $cfg['title']);
            $sheet->getColumnDimension($col)->setWidth($cfg['width']);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10, 'name' => 'Arial'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']], // Indigo 600
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '4338CA'],
                ],
            ],
        ];
        $sheet->getStyle('A5:R5')->applyFromArray($headerStyle);
        $sheet->getRowDimension(5)->setRowHeight(30);

        // 4. Llenado de Filas de Datos
        $startRow = 6;
        $row = $startRow;

        if (count($items) === 0) {
            $sheet->mergeCells("A{$row}:R{$row}");
            $sheet->setCellValue("A{$row}", 'No se encontraron registros de inventario con los criterios seleccionados.');
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(10)->getColor()->setRGB('64748B');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension($row)->setRowHeight(32);
            $row++;
        } else {
            $contador = 1;
            foreach ($items as $item) {
                $sedeItem = $item->sede?->nombre ?? '—';
                $dependenciaProceso = $item->proceso?->nombre ?? ($item->dependencia ?: '—');
                $responsable = $item->responsablePersonal?->nombre ?? ($item->responsable ?: '—');
                $coordinador = $item->coordinadorPersonal?->nombre ?? '—';
                $fechaCompra = $item->fecha_compra ? Carbon::parse($item->fecha_compra)->format('d/m/Y') : '—';
                $valorCompra = ($item->valor_compra !== null && $item->valor_compra !== '') ? (float) $item->valor_compra : 0;

                $sheet->setCellValue('A' . $row, $contador++);
                $sheet->setCellValue('B' . $row, $item->codigo ?? '—');
                $sheet->setCellValue('C' . $row, $item->nombre ?? '—');
                $sheet->setCellValue('D' . $row, $item->marca ?? '—');
                $sheet->setCellValue('E' . $row, $item->modelo ?? '—');
                $sheet->setCellValue('F' . $row, $item->serial ?? '—');
                $sheet->setCellValue('G' . $row, $sedeItem);
                $sheet->setCellValue('H' . $row, $dependenciaProceso);
                $sheet->setCellValue('I' . $row, $item->ubicacion ?? '—');
                $sheet->setCellValue('J' . $row, $responsable);
                $sheet->setCellValue('K' . $row, $coordinador);
                $sheet->setCellValue('L' . $row, $item->estado ?? '—');
                $sheet->setCellValue('M' . $row, $fechaCompra);
                $sheet->setCellValue('N' . $row, $valorCompra);
                $sheet->setCellValue('O' . $row, $item->num_factu ?? '—');
                $sheet->setCellValue('P' . $row, $item->proveedor ?? '—');
                $sheet->setCellValue('Q' . $row, $item->codigo_barras ?? '—');
                $sheet->setCellValue('R' . $row, $item->observaciones ?? '—');

                // Zebra row styling
                $isEven = ($row % 2 === 0);
                $rowBg = $isEven ? 'FFFFFF' : 'F8FAFC';
                $sheet->getStyle("A{$row}:R{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rowBg);
                $sheet->getRowDimension($row)->setRowHeight(22);

                $row++;
            }
        }

        $endRow = $row - 1;

        // Formatos y alineaciones para celdas de datos
        if (count($items) > 0) {
            $sheet->getStyle("A{$startRow}:R{$endRow}")->applyFromArray([
                'font' => ['size' => 9.5, 'name' => 'Arial'],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => 'E2E8F0'],
                    ],
                ],
            ]);

            // Alineaciones específicas
            $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$startRow}:B{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$startRow}:B{$endRow}")->getFont()->setBold(true);

            $sheet->getStyle("C{$startRow}:E{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
            $sheet->getStyle("F{$startRow}:F{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$startRow}:K{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
            $sheet->getStyle("L{$startRow}:M{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Moneda para Valor Compra
            $sheet->getStyle("N{$startRow}:N{$endRow}")->getNumberFormat()->setFormatCode('"$"#,##0');
            $sheet->getStyle("N{$startRow}:N{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setIndent(1);

            $sheet->getStyle("O{$startRow}:O{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("P{$startRow}:P{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
            $sheet->getStyle("Q{$startRow}:Q{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("R{$startRow}:R{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);

            // Activar autofiltro y fijar encabezados
            $sheet->setAutoFilter("A5:R{$endRow}");
        }

        $sheet->freezePane('A6');

        return $spreadsheet;
    }

    /**
     * Construcción de hoja de cálculo optimizada para PDF en Orientación Horizontal (Landscape).
     */
    private function buildPdfSpreadsheet($items, ?string $sedeNombre = null, ?string $extraInfo = null): array
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventario');

        // Título General (Fila 2)
        $sheet->mergeCells('A2:L2');
        $sheet->setCellValue('A2', 'NEXA - GESTIÓN DE COMPRAS: REPORTE DE INVENTARIO');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13)->setName('Arial')->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('312E81');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(2)->setRowHeight(34);

        // Subtítulo con sede y metadatos (Fila 3)
        $sedeLabel = $sedeNombre ? mb_strtoupper($sedeNombre, 'UTF-8') : 'TODAS LAS SEDES';
        $subtitulo = "SEDE: {$sedeLabel}   |   GENERADO: " . Carbon::now('America/Bogota')->format('d/m/Y h:i A') . "   |   TOTAL: " . count($items);
        if (!empty($extraInfo)) {
            $subtitulo .= "   |   FILTROS: {$extraInfo}";
        }

        $sheet->mergeCells('A3:L3');
        $sheet->setCellValue('A3', $subtitulo);
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(9)->setName('Arial')->getColor()->setRGB('3730A3');
        $sheet->getStyle('A3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2FF');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(3)->setRowHeight(24);

        // Fila 4: Separador
        $sheet->getRowDimension(4)->setRowHeight(6);

        // Encabezados de Columnas para PDF (Fila 5)
        $pdfHeaders = [
            'A' => ['title' => '#', 'width' => 5],
            'B' => ['title' => 'Código', 'width' => 14],
            'C' => ['title' => 'Nombre del Activo', 'width' => 28],
            'D' => ['title' => 'Marca / Modelo', 'width' => 20],
            'E' => ['title' => 'Serial', 'width' => 16],
            'F' => ['title' => 'Sede', 'width' => 20],
            'G' => ['title' => 'Dependencia / Proceso', 'width' => 22],
            'H' => ['title' => 'Ubicación', 'width' => 16],
            'I' => ['title' => 'Responsable', 'width' => 22],
            'J' => ['title' => 'Estado', 'width' => 11],
            'K' => ['title' => 'F. Compra', 'width' => 12],
            'L' => ['title' => 'Valor Compra', 'width' => 15],
        ];

        foreach ($pdfHeaders as $col => $cfg) {
            $sheet->setCellValue($col . '5', $cfg['title']);
            $sheet->getColumnDimension($col)->setWidth($cfg['width']);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9, 'name' => 'Arial'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '4338CA'],
                ],
            ],
        ];
        $sheet->getStyle('A5:L5')->applyFromArray($headerStyle);
        $sheet->getRowDimension(5)->setRowHeight(28);

        $startRow = 6;
        $row = $startRow;

        if (count($items) === 0) {
            $sheet->mergeCells("A{$row}:L{$row}");
            $sheet->setCellValue("A{$row}", 'No se encontraron registros de inventario con los criterios seleccionados.');
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('64748B');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension($row)->setRowHeight(28);
            $row++;
        } else {
            $contador = 1;
            foreach ($items as $item) {
                $sedeItem = $item->sede?->nombre ?? '—';
                $dependenciaProceso = $item->proceso?->nombre ?? ($item->dependencia ?: '—');
                $marcaModelo = trim(($item->marca ?? '') . ' ' . ($item->modelo ?? '')) ?: '—';
                $responsable = $item->responsablePersonal?->nombre ?? ($item->responsable ?: '—');
                $fechaCompra = $item->fecha_compra ? Carbon::parse($item->fecha_compra)->format('d/m/Y') : '—';
                $valorCompra = ($item->valor_compra !== null && $item->valor_compra !== '') ? (float) $item->valor_compra : 0;

                $sheet->setCellValue('A' . $row, $contador++);
                $sheet->setCellValue('B' . $row, $item->codigo ?? '—');
                $sheet->setCellValue('C' . $row, $item->nombre ?? '—');
                $sheet->setCellValue('D' . $row, $marcaModelo);
                $sheet->setCellValue('E' . $row, $item->serial ?? '—');
                $sheet->setCellValue('F' . $row, $sedeItem);
                $sheet->setCellValue('G' . $row, $dependenciaProceso);
                $sheet->setCellValue('H' . $row, $item->ubicacion ?? '—');
                $sheet->setCellValue('I' . $row, $responsable);
                $sheet->setCellValue('J' . $row, $item->estado ?? '—');
                $sheet->setCellValue('K' . $row, $fechaCompra);
                $sheet->setCellValue('L' . $row, $valorCompra);

                $isEven = ($row % 2 === 0);
                $rowBg = $isEven ? 'FFFFFF' : 'F8FAFC';
                $sheet->getStyle("A{$row}:L{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rowBg);
                $sheet->getRowDimension($row)->setRowHeight(20);

                $row++;
            }
        }

        $endRow = $row - 1;

        if (count($items) > 0) {
            $sheet->getStyle("A{$startRow}:L{$endRow}")->applyFromArray([
                'font' => ['size' => 8.5, 'name' => 'Arial'],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => 'E2E8F0'],
                    ],
                ],
            ]);

            $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$startRow}:B{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$startRow}:B{$endRow}")->getFont()->setBold(true);
            $sheet->getStyle("C{$startRow}:D{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
            $sheet->getStyle("E{$startRow}:E{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$startRow}:I{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
            $sheet->getStyle("J{$startRow}:K{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("L{$startRow}:L{$endRow}")->getNumberFormat()->setFormatCode('"$"#,##0');
            $sheet->getStyle("L{$startRow}:L{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setIndent(1);
        }

        // Configuración de página para PDF
        $sheet->getPageMargins()->setTop(0.35);
        $sheet->getPageMargins()->setBottom(0.35);
        $sheet->getPageMargins()->setLeft(0.25);
        $sheet->getPageMargins()->setRight(0.25);

        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 5);
        $sheet->getPageSetup()->setHorizontalCentered(true);
        $sheet->getPageSetup()->setVerticalCentered(false);
        $sheet->getPageSetup()->setPrintArea("A1:L{$endRow}");

        return [$spreadsheet, $endRow];
    }

    /**
     * Fallback de conversión local a PDF mediante mPDF.
     */
    private function convertDirectlyToPdf(string $excelFilePath, int $highestRow): string
    {
        $spreadsheet = IOFactory::load($excelFilePath);
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->getPageSetup()->setPrintArea("A1:L{$highestRow}");
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        while ($spreadsheet->getSheetCount() > 1) {
            $spreadsheet->removeSheetByIndex(1);
        }

        $tempPdfPath = tempnam(sys_get_temp_dir(), 'inv_pdf_mpdf_') . '.pdf';
        IOFactory::registerWriter('Pdf', \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf::class);
        $writer = IOFactory::createWriter($spreadsheet, 'Pdf');
        $writer->save($tempPdfPath);
        $spreadsheet->disconnectWorksheets();

        $content = file_get_contents($tempPdfPath);
        @unlink($tempPdfPath);

        return $content;
    }
}
