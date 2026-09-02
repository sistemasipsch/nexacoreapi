<?php

namespace App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Exception;

class ExportarCronogramaMantenimientoEquiposExcelUseCase
{
    public function __construct(
        protected ObtenerCronogramaExportacionDTOUseCase $obtenerDatosUseCase
    ) {}

    public function execute(): string
    {
        $dtos = $this->obtenerDatosUseCase->execute();
        $templatePath = storage_path('app/templates/plantilla_cronograma_mantenimiento_equipos.xlsx');
        
        if (!file_exists($templatePath)) {
            throw new Exception('No se encontró la plantilla de cronograma de mantenimientos.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        $row = 9; // Fila de inicio
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
            $sheet->getStyle("B9:B{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I9:K{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("N9:W{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }

        $filename = 'cronograma_mantenimientos_' . time() . '.xlsx';
        $exportDir = storage_path('app/public/exports');
        
        if (!file_exists($exportDir)) {
            mkdir($exportDir, 0777, true);
        }
        
        $exportPath = $exportDir . '/' . $filename;
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($exportPath);
        $spreadsheet->disconnectWorksheets();

        return $filename;
    }
}
