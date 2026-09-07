<?php

namespace App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos;

use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Exception;

class ExportarCronogramaMantenimientoEquiposPdfUseCase
{
    public function __construct(
        protected ExcelToPdfConverterInterface $pdfConverter,
        protected ObtenerCronogramaExportacionDTOUseCase $obtenerDatosUseCase
    ) {}

    public function execute(?int $sedeId = null, ?string $tipoMantenimiento = null): string
    {
        $dtos = $this->obtenerDatosUseCase->execute($sedeId, $tipoMantenimiento);

        $templatePath = storage_path('app/templates/plantilla_cronograma_mantenimiento_equipos.xlsx');
        
        if (!file_exists($templatePath)) {
            throw new Exception('No se encontró la plantilla de cronograma de mantenimientos.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        $tipoLabel = '';
        if ($tipoMantenimiento && !in_array($tipoMantenimiento, ['all', 'todos', 'todas', ''], true)) {
            $tipoLabel = ' (' . mb_strtoupper($tipoMantenimiento) . ')';
        }

        if ($sedeId) {
            $sede = \App\Models\Sede::find($sedeId);
            if ($sede) {
                $sheet->setCellValue('D2', 'CRONOGRAMA DE MANTENIMIENTOS' . $tipoLabel . ' - ' . mb_strtoupper($sede->nombre));
            }
        } else {
            $sheet->setCellValue('D2', 'CRONOGRAMA DE MANTENIMIENTOS' . $tipoLabel . ' - TODAS LAS SEDES');
        }

        $row = 9;

        if (count($dtos) === 0) {
            $sheet->setCellValue('B9', 1);
            $msg = 'NO SE ENCONTRARON EQUIPOS CON MANTENIMIENTOS REGISTRADOS';
            if ($sedeId) {
                $msg .= ' PARA ESTA SEDE';
            }
            if ($tipoMantenimiento && !in_array($tipoMantenimiento, ['all', 'todos', 'todas', ''], true)) {
                $msg .= ' DE TIPO ' . mb_strtoupper($tipoMantenimiento);
            }
            $sheet->setCellValue('C9', $msg);
            $sheet->mergeCells('C9:W9');
            $sheet->getStyle('C9')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C9')->getFont()->setBold(true)->setName('Arial')->setSize(9.5);
            $sheet->getRowDimension(9)->setRowHeight(22);
            $endRow = 9;
            $row = 10;
        } else {
            $contador = 1;

            foreach ($dtos as $dto) {
                $sheet->setCellValue('B' . $row, $contador++);
                $sheet->setCellValue('C' . $row, $dto->equipoComputo);
                $sheet->setCellValue('D' . $row, $dto->marca);
                $sheet->setCellValue('E' . $row, $dto->modelo);
                $sheet->setCellValue('F' . $row, $dto->sede);
                $sheet->setCellValue('G' . $row, $dto->area);
                $sheet->setCellValue('H' . $row, $dto->serial);
                $sheet->setCellValue('I' . $row, $dto->propioArriendo);
                $sheet->setCellValue('J' . $row, $dto->ipFijaLocal);
                $sheet->setCellValue('K' . $row, $dto->numeroInventario);
                $sheet->setCellValue('L' . $row, $dto->procesador);
                $sheet->setCellValue('M' . $row, $dto->memoriaRam);
                $sheet->setCellValue('N' . $row, $dto->paraCumplimiento);
                $sheet->setCellValue('O' . $row, $dto->fechaUltimoMantenimiento2024);
                $sheet->setCellValue('P' . $row, $dto->hoy);
                $sheet->setCellValue('Q' . $row, $dto->dias);
                $sheet->setCellValue('R' . $row, $dto->vencimiento);
                $sheet->setCellValue('S' . $row, $dto->fechaProgramada);
                $sheet->setCellValue('T' . $row, $dto->ejecucion);
                $sheet->setCellValue('U' . $row, $dto->fechaUltimoMantenimiento2025IISemestre);
                $sheet->setCellValue('V' . $row, $dto->fechaUltimoMantenimiento2026ISemestre);
                $sheet->setCellValue('W' . $row, $dto->estadoMantenimiento);

                $sheet->getRowDimension($row)->setRowHeight(19.5);
                $row++;
            }

            $endRow = $row - 1;
        }

        // Truncar y limpiar cualquier fila sobrante de la plantilla
        $highestRow = max(1000, $sheet->getHighestRow());
        if ($highestRow >= $row) {
            $sheet->removeRow($row, $highestRow - $row + 1);
            foreach (array_keys($sheet->getRowDimensions()) as $r) {
                if ($r >= $row) {
                    $sheet->removeRowDimension($r);
                }
            }
            $sheet->garbageCollect();
        }

        if ($endRow >= 9) {
            $sheet->getStyle("B9:W{$endRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
                'alignment' => [
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ]);

            $sheet->getStyle("B9:W{$endRow}")->getFont()->setName('Arial')->setSize(9);

            if (count($dtos) > 0) {
                // Columnas de texto: C, D, E, F, G, L -> Todas a la IZQUIERDA con indentación limpia (encabezado y datos)
                $sheet->getStyle("C8:G{$endRow}")->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
                    ->setIndent(1);

                $sheet->getStyle("L8:L{$endRow}")->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
                    ->setIndent(1);

                // Columnas de códigos, números, fechas y estados: B, H..K, M..W -> Todas CENTRADAS (encabezado y datos)
                $sheet->getStyle("B8:B{$endRow}")->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("H8:K{$endRow}")->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("M8:W{$endRow}")->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            }
        }

        // Encabezado fila 8 con ajuste de texto y centrado vertical
        $sheet->getStyle("B8:W8")->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // Asegurar bordes derechos completos en toda la tabla y encabezados (W2 a WendRow)
        for ($r = 2; $r <= 6; $r++) {
            $sheet->getStyle("W{$r}")->getBorders()->getRight()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        }
        $sheet->getStyle("B8:W8")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);
        if ($endRow >= 9) {
            $sheet->getStyle("W9:W{$endRow}")->getBorders()->getRight()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        }

        // Configuración de página para PDF
        $sheet->getPageMargins()->setTop(0.4);
        $sheet->getPageMargins()->setBottom(0.4);
        $sheet->getPageMargins()->setLeft(0.3);
        $sheet->getPageMargins()->setRight(0.3);

        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0); // Permite flujo multi-página vertical
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 8); // Repite encabezados en cada página del PDF
        $sheet->getPageSetup()->setVerticalCentered(false);
        $sheet->getPageSetup()->setHorizontalCentered(true);
        $sheet->getPageSetup()->setPrintArea("B1:W{$endRow}");

        while ($spreadsheet->getSheetCount() > 1) {
            $activeIndex = $spreadsheet->getActiveSheetIndex();
            $indexToRemove = $activeIndex === 0 ? 1 : 0;
            $spreadsheet->removeSheetByIndex($indexToRemove);
        }

        $filename = 'cronograma_mantenimientos_' . ($sedeId ? 'sede_' . $sedeId . '_' : '') . ($tipoMantenimiento ? $tipoMantenimiento . '_' : '') . time() . '.pdf';
        $tempExcelPath = tempnam(sys_get_temp_dir(), 'cronograma_excel_') . '.xlsx';
        
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
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
}
