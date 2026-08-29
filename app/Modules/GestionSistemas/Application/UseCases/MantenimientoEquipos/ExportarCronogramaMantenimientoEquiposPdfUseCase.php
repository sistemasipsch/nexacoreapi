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

    public function execute(): string
    {
        $dtos = $this->obtenerDatosUseCase->execute();

        $templatePath = storage_path('app/templates/plantilla_cronograma_mantenimiento_equipos.xlsx');
        
        if (!file_exists($templatePath)) {
            throw new Exception('No se encontró la plantilla de cronograma de mantenimientos.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        $row = 9;
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
            $row++;
        }

        $endRow = $row - 1;
        if ($endRow >= 9) {
            $sheet->getStyle("A9:V{$endRow}")->applyFromArray([
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

        $filename = 'cronograma_mantenimientos_' . time() . '.pdf';
        $tempExcelPath = tempnam(sys_get_temp_dir(), 'cronograma_excel_') . '.xlsx';
        
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
