<?php

namespace App\Modules\GestionSistemas\Application\UseCases\ActasDevolucion;

use App\Models\PcDevuelto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ExportarActaDevolucionExcelUseCase
{
    public function execute(int $id): string
    {
        $devolucion = PcDevuelto::with([
            'entrega.equipo',
            'entrega.funcionario.cargo',
            'entrega.perifericos.inventario'
        ])->findOrFail($id);

        $entrega = $devolucion->entrega;

        $templatePath = storage_path('app/templates/plantilla_devolucion_equipo.xlsx');
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Datos del funcionario
        $nombre = optional($entrega->funcionario)->nombre ?? '';
        $cedula = optional($entrega->funcionario)->cedula ?? '';
        $cargo = optional(optional($entrega->funcionario)->cargo)->nombre ?? '';
        $telefono = optional($entrega->funcionario)->telefono ?? '';
        $proceso = ''; // Proceso - a definir

        $sheet->setCellValue('T7', 'NOMBRE: ' . $nombre);
        $sheet->setCellValue('T8', 'NUMERO DE IDENTIFICACION: ' . $cedula);
        $sheet->setCellValue('T9', 'CARGO: ' . $cargo);
        $sheet->setCellValue('T10', 'TELEFONO: ' . $telefono);
        $sheet->setCellValue('T11', 'PROCESO: ' . $proceso);

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
            
            // Insertar Firmas si existen (Solo en la primera fila o donde se requiera)
            $this->insertarFirma($sheet, $devolucion->firma_entrega, 'AD' . $row);
            $this->insertarFirma($sheet, $devolucion->firma_recibe, 'AG' . $row);
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
                
                $row++;
            }
        }

        $fileName = 'acta_devolucion_' . $devolucion->id . '_' . time() . '.xlsx';
        $exportPath = storage_path('app/public/exports/' . $fileName);
        
        if (!file_exists(storage_path('app/public/exports'))) {
            mkdir(storage_path('app/public/exports'), 0777, true);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($exportPath);

        return $fileName;
    }

    private function insertarFirma($sheet, $path, $cell)
    {
        $cleanPath = $path ? ltrim(str_replace(['storage/', 'public/'], '', $path), '/') : null;
        if ($cleanPath && Storage::disk('public')->exists($cleanPath)) {
            $drawing = new Drawing();
            $drawing->setName('Firma');
            $drawing->setDescription('Firma');
            $drawing->setPath(storage_path('app/public/' . $cleanPath));
            $drawing->setCoordinates($cell);
            $drawing->setHeight(40); // Ajustar según el tamaño de la celda
            $drawing->setWorksheet($sheet);
        }
    }
}
