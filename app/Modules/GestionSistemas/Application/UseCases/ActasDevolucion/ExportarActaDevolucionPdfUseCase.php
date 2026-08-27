<?php

namespace App\Modules\GestionSistemas\Application\UseCases\ActasDevolucion;

use App\Models\PcDevuelto;
use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Exception;

class ExportarActaDevolucionPdfUseCase
{
    public function __construct(
        protected ExcelToPdfConverterInterface $pdfConverter
    ) {}

    public function execute(int $id): string
    {
        $devolucion = PcDevuelto::with([
            'entrega.equipo',
            'entrega.funcionario.cargo',
            'entrega.perifericos.inventario'
        ])->findOrFail($id);

        $entrega = $devolucion->entrega;

        $templatePath = storage_path('app/templates/plantilla_devolucion_equipo.xlsx');
        
        if (!file_exists($templatePath)) {
            throw new Exception('No se encontró la plantilla de acta de devolución.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Datos del funcionario
        $nombre = optional($entrega->funcionario)->nombre ?? '';
        $cedula = optional($entrega->funcionario)->cedula ?? '';
        $cargo = optional(optional($entrega->funcionario)->cargo)->nombre ?? '';
        $telefono = optional($entrega->funcionario)->telefono ?? '';
        $proceso = ''; // Proceso - a definir

        $sheet->setCellValue('V7', 'NOMBRE: ' . $nombre);
        $sheet->setCellValue('V8', 'NUMERO DE IDENTIFICACION: ' . $cedula);
        $sheet->setCellValue('V9', 'CARGO: ' . $cargo);
        $sheet->setCellValue('V10', 'TELEFONO: ' . $telefono);
        $sheet->setCellValue('V11', 'PROCESO: ' . $proceso);

        $fecha = Carbon::parse($devolucion->fecha_devolucion);
        $row = 14;

        // Fila 14: Equipo Principal
        if ($entrega && $entrega->equipo) {
            $sheet->setCellValue('B' . $row, $fecha->format('Y'));
            $sheet->setCellValue('D' . $row, $fecha->format('m'));
            $sheet->setCellValue('E' . $row, $fecha->format('d'));
            $sheet->setCellValue('F' . $row, $entrega->equipo->nombre_equipo ?? 'Equipo PC');
            $sheet->setCellValue('O' . $row, 1);
            $sheet->setCellValue('R' . $row, $entrega->equipo->marca ?? '');
            $sheet->setCellValue('V' . $row, $entrega->equipo->modelo ?? '');
            $sheet->setCellValue('Z' . $row, $entrega->equipo->serial ?? '');
            $sheet->setCellValue('AJ' . $row, $fecha->format('Y-m-d'));
            
            // Insertar Firmas si existen
            
            $row++;
        }

        // Perifericos
        if ($entrega && $entrega->perifericos) {
            foreach ($entrega->perifericos as $periferico) {
                $sheet->setCellValue('B' . $row, $fecha->format('Y'));
                $sheet->setCellValue('D' . $row, $fecha->format('m'));
                $sheet->setCellValue('E' . $row, $fecha->format('d'));
                $sheet->setCellValue('F' . $row, optional($periferico->inventario)->nombre ?? 'Periférico');
                $sheet->setCellValue('O' . $row, $periferico->cantidad ?? 1);
                $sheet->setCellValue('R' . $row, optional($periferico->inventario)->marca ?? '');
                $sheet->setCellValue('V' . $row, optional($periferico->inventario)->modelo ?? '');
                $sheet->setCellValue('Z' . $row, optional($periferico->inventario)->serial ?? '');
                $sheet->setCellValue('AJ' . $row, $fecha->format('Y-m-d'));
                $this->insertarFirma($sheet, $devolucion->firma_entrega, 'AD' . $row);
                $this->insertarFirma($sheet, $devolucion->firma_recibe, 'AG' . $row);

                $row++;
            }
        }

        // Remover otras hojas para evitar que LibreOffice genere páginas extra en el PDF
        while ($spreadsheet->getSheetCount() > 1) {
            $activeIndex = $spreadsheet->getActiveSheetIndex();
            $indexToRemove = $activeIndex === 0 ? 1 : 0;
            $spreadsheet->removeSheetByIndex($indexToRemove);
        }

        $funcionarioName = $this->sanitize(optional($entrega->funcionario)->nombre ?? 'SIN_NOMBRE');
        $filename = 'acta_devolucion_' . $devolucion->id . '_' . time() . '.pdf';

        $tempExcelPath = tempnam(sys_get_temp_dir(), 'acta_excel_') . '.xlsx';
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

    private function insertarFirma($sheet, $path, $cell)
    {
        if ($path && Storage::disk('public')->exists($path)) {
            $drawing = new Drawing();
            $drawing->setName('Firma');
            $drawing->setDescription('Firma');
            $drawing->setPath(storage_path('app/public/' . $path));
            $drawing->setCoordinates($cell);
            $drawing->setHeight(30); // Ajustar según el tamaño de la celda
            $drawing->setWorksheet($sheet);
        }
    }

    private function sanitize(string $string): string
    {
        $string = preg_replace('/[^A-Za-z0-9\-\s]/', '', $string);
        return trim(preg_replace('/\s+/', '_', $string));
    }
}
