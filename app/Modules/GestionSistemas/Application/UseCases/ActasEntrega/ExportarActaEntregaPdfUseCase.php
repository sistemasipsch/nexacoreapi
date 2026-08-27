<?php

namespace App\Modules\GestionSistemas\Application\UseCases\ActasEntrega;

use App\Models\PcEntrega;
use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Exception;

class ExportarActaEntregaPdfUseCase
{
    public function __construct(
        protected ExcelToPdfConverterInterface $pdfConverter
    ) {}

    public function execute(int $id): string
    {
        $acta = PcEntrega::with([
            'equipo',
            'funcionario.cargo',
            'perifericos.inventario'
        ])->findOrFail($id);

        $templatePath = storage_path('app/templates/plantilla_acta_entrega_equipos.xlsx');
        
        if (!file_exists($templatePath)) {
            throw new Exception('No se encontró la plantilla de acta de entrega.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Datos del funcionario
        $nombre = optional($acta->funcionario)->nombre ?? '';
        $cedula = optional($acta->funcionario)->cedula ?? '';
        $cargo = optional(optional($acta->funcionario)->cargo)->nombre ?? '';
        $telefono = optional($acta->funcionario)->telefono ?? '';
        $proceso = ''; // Proceso - a definir

        $sheet->setCellValue('T7', 'NOMBRE: ' . $nombre);
        $sheet->setCellValue('T8', 'NUMERO DE IDENTIFICACION: ' . $cedula);
        $sheet->setCellValue('T9', 'CARGO: ' . $cargo);
        $sheet->setCellValue('T10', 'TELEFONO: ' . $telefono);
        $sheet->setCellValue('T11', 'PROCESO: ' . $proceso);

        $fecha = Carbon::parse($acta->fecha_entrega);
        $row = 14;

        $firmaEntrega = $acta->firma_entrega;
        $firmaPersonalRecibe = optional($acta->funcionario)->firma;
        $firmaRecibe = $acta->firma_recibe;

        // Fila 14: Equipo Principal
        if ($acta->equipo) {
            $sheet->setCellValue('B' . $row, $fecha->format('Y'));
            $sheet->setCellValue('D' . $row, $fecha->format('m'));
            $sheet->setCellValue('E' . $row, $fecha->format('d'));
            $sheet->setCellValue('F' . $row, $acta->equipo->nombre_equipo ?? 'Equipo PC');
            $sheet->setCellValue('O' . $row, 1);
            $sheet->setCellValue('R' . $row, $acta->equipo->marca ?? '');
            $sheet->setCellValue('V' . $row, $acta->equipo->modelo ?? '');
            $sheet->setCellValue('Z' . $row, $acta->equipo->serial ?? '');
            $sheet->setCellValue('AJ' . $row, $acta->devuelto ? Carbon::parse($acta->devuelto)->format('Y-m-d') : '');
            
            $this->insertarFirma($sheet, $firmaEntrega, 'AD' . $row);
            $this->insertarFirma($sheet, $firmaRecibe, 'AG' . $row, $firmaPersonalRecibe);

            $row++;
        }

        // Perifericos
        if ($acta->perifericos) {
            foreach ($acta->perifericos as $periferico) {
                $sheet->setCellValue('B' . $row, $fecha->format('Y'));
                $sheet->setCellValue('D' . $row, $fecha->format('m'));
                $sheet->setCellValue('E' . $row, $fecha->format('d'));
                $sheet->setCellValue('F' . $row, optional($periferico->inventario)->nombre ?? 'Periférico');
                $sheet->setCellValue('O' . $row, $periferico->cantidad ?? 1);
                $sheet->setCellValue('R' . $row, optional($periferico->inventario)->marca ?? '');
                $sheet->setCellValue('V' . $row, optional($periferico->inventario)->modelo ?? '');
                $sheet->setCellValue('Z' . $row, optional($periferico->inventario)->serial ?? '');
                $sheet->setCellValue('AJ' . $row, $acta->devuelto ? Carbon::parse($acta->devuelto)->format('Y-m-d') : '');
                
                $this->insertarFirma($sheet, $firmaEntrega, 'AD' . $row);
                $this->insertarFirma($sheet, $firmaRecibe, 'AG' . $row, $firmaPersonalRecibe);
                $row++;
            }
        }

        // Remover otras hojas para evitar que LibreOffice genere páginas extra en el PDF
        while ($spreadsheet->getSheetCount() > 1) {
            $activeIndex = $spreadsheet->getActiveSheetIndex();
            $indexToRemove = $activeIndex === 0 ? 1 : 0;
            $spreadsheet->removeSheetByIndex($indexToRemove);
        }

        $funcionarioName = $this->sanitize(optional($acta->funcionario)->nombre ?? 'SIN_NOMBRE');
        $filename = 'acta_entrega_' . $acta->id . '_' . time() . '.pdf';

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

    private function insertarFirma($sheet, $path, $cell, $fallbackPath = null)
    {
        $cleanPath = $path ? ltrim(str_replace(['storage/', 'public/'], '', $path), '/') : null;
        if ($cleanPath && Storage::disk('public')->exists($cleanPath)) {
            $drawing = new Drawing();
            $drawing->setName('Firma');
            $drawing->setDescription('Firma');
            $drawing->setPath(storage_path('app/public/' . $cleanPath));
            $drawing->setCoordinates($cell);
            $drawing->setHeight(25);
            $drawing->setWorksheet($sheet);
            return;
        }

        if ($fallbackPath) {
            $cleanFallback = ltrim(str_replace(['storage/', 'public/'], '', $fallbackPath), '/');
            if (Storage::disk('public')->exists($cleanFallback)) {
                $drawing = new Drawing();
                $drawing->setName('Firma');
                $drawing->setDescription('Firma');
                $drawing->setPath(storage_path('app/public/' . $cleanFallback));
                $drawing->setCoordinates($cell);
                $drawing->setHeight(25);
                $drawing->setWorksheet($sheet);
            }
        }
    }

    private function sanitize(string $string): string
    {
        $string = preg_replace('/[^A-Za-z0-9\-\s]/', '', $string);
        return trim(preg_replace('/\s+/', '_', $string));
    }
}
