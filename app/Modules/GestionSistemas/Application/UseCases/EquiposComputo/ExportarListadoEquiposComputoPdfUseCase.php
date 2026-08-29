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
            
            $row++;
        }

        $endRow = $row - 1;
        if ($endRow >= 9) {
            $sheet->getStyle("B9:Y{$endRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ]);
        }

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
