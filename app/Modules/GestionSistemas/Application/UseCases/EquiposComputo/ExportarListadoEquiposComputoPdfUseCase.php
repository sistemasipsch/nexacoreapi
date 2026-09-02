<?php

namespace App\Modules\GestionSistemas\Application\UseCases\EquiposComputo;

use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Exception;

class ExportarListadoEquiposComputoPdfUseCase
{
    public function __construct(
        protected ExcelToPdfConverterInterface $pdfConverter,
        protected ObtenerListadoEquiposDTOUseCase $obtenerDatosUseCase
    ) {}

    public function execute(): string
    {
        $dtos = $this->obtenerDatosUseCase->execute();

        $templatePath = storage_path('app/templates/plantilla_listado_equipos_computo.xlsx');
        
        if (!file_exists($templatePath)) {
            throw new Exception('No se encontró la plantilla de listado de equipos.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        $row = 9; // Suponiendo que la fila 1 tiene los encabezados

        $contador = 1;
        foreach ($dtos as $dto) {
            $sheet->setCellValue('B' . $row, $contador++);
            $sheet->setCellValue('C' . $row, $dto->equipoComputo);
            $sheet->setCellValue('D' . $row, $dto->marca);
            $sheet->setCellValue('E' . $row, $dto->modelo);
            $sheet->setCellValue('F' . $row, $dto->area);
            $sheet->setCellValue('G' . $row, $dto->personalEncargado);
            $sheet->setCellValue('H' . $row, $dto->serial);
            $sheet->setCellValue('J' . $row, $dto->propiedadEmpleado);
            $sheet->setCellValue('K' . $row, $dto->ipFijaLocal);
            $sheet->setCellValue('L' . $row, $dto->numeroInventario);
            $sheet->setCellValue('M' . $row, $dto->sede);
            $sheet->setCellValue('N' . $row, $dto->procesador);
            $sheet->setCellValue('O' . $row, $dto->memoriaRam);
            $sheet->setCellValue('P' . $row, $dto->windows);
            $sheet->setCellValue('Q' . $row, $dto->office);
            $sheet->setCellValue('R' . $row, $dto->nitro);
            $sheet->setCellValue('S' . $row, $dto->paraCumplimiento);
            $sheet->setCellValue('T' . $row, $dto->fechaUltimoMantenimiento);
            $sheet->setCellValue('U' . $row, $dto->hoy);
            $sheet->setCellValue('V' . $row, $dto->dias);
            $sheet->setCellValue('W' . $row, $dto->vencimiento);
            $sheet->setCellValue('X' . $row, $dto->proximaFecha);
            $sheet->setCellValue('Y' . $row, $dto->estadoProgramacion);

            $sheet->mergeCells("H{$row}:I{$row}");
            $sheet->getStyle("H{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_JUSTIFY);
            
            // Asegurar que cada fila tenga exactamente la misma altura (24pt)
            $sheet->getRowDimension($row)->setRowHeight(24);
            
            $row++;
        }

        $endRow = $row - 1;
        if ($endRow >= 9) {
            // Aplicar estilo uniforme de fuente, alineación y bordes a todas las filas
            $sheet->getStyle("B9:Y{$endRow}")->applyFromArray([
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

            $sheet->getStyle("B9:Y{$endRow}")->getFont()->setName('Arial')->setSize(9.5);
            $sheet->getStyle("B9:B{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("J9:L{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("O9:Y{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
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
        $sheet->getPageSetup()->setFitToHeight(0); // 0 permite fluir verticalmente en varias páginas sin aplastar celdas
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 8); // Repite encabezados en cada página del PDF

        while ($spreadsheet->getSheetCount() > 1) {
            $activeIndex = $spreadsheet->getActiveSheetIndex();
            $indexToRemove = $activeIndex === 0 ? 1 : 0;
            $spreadsheet->removeSheetByIndex($indexToRemove);
        }

        $filename = 'listado_equipos_' . time() . '.pdf';
        $tempExcelPath = tempnam(sys_get_temp_dir(), 'listado_excel_') . '.xlsx';
        
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
}
