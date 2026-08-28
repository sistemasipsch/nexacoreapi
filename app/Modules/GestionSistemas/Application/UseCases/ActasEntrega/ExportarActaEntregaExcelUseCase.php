<?php

namespace App\Modules\GestionSistemas\Application\UseCases\ActasEntrega;

use App\Models\PcEntrega;
use App\Models\Usuario;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Exception;

class ExportarActaEntregaExcelUseCase
{
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

        // 1. Datos del funcionario
        $nombre = optional($acta->funcionario)->nombre ?? '';
        $cedula = optional($acta->funcionario)->cedula ?? '';
        $cargo = optional(optional($acta->funcionario)->cargo)->nombre ?? (is_string($acta->funcionario?->cargo) ? $acta->funcionario->cargo : '');
        $telefono = optional($acta->funcionario)->telefono ?? '';
        $proceso = '';

        $sheet->setCellValue('T7', 'NOMBRE: ' . $nombre);
        $sheet->setCellValue('T8', 'NUMERO DE IDENTIFICACION: ' . $cedula);
        $sheet->setCellValue('T9', 'CARGO: ' . $cargo);
        $sheet->setCellValue('T10', 'TELEFONO: ' . $telefono);
        $sheet->setCellValue('T11', 'PROCESO: ' . $proceso);

        // 2. Firmas disponibles con fallback
        $firmaEntrega = $acta->getRawOriginal('firma_entrega') ?? $acta->attributes['firma_entrega'] ?? null;
        $adminFallback = null;
        $admin = Usuario::whereNotNull('firma_digital')->where('firma_digital', '!=', '')->first();
        if ($admin) {
            $adminFallback = $admin->getRawOriginal('firma_digital');
        }

        $firmaRecibe = $acta->getRawOriginal('firma_recibe') ?? $acta->attributes['firma_recibe'] ?? null;
        $funcionarioFallback = optional($acta->funcionario)->getRawOriginal('firma') ?? optional($acta->funcionario)->firma ?? null;

        // 3. Preparar lista de items (Equipo + Periféricos)
        $items = [];
        if ($acta->equipo) {
            $items[] = [
                'es_equipo' => true,
                'nombre' => $acta->equipo->nombre_equipo ?? 'Equipo PC',
                'cantidad' => 1,
                'marca' => $acta->equipo->marca ?? '',
                'modelo' => $acta->equipo->modelo ?? '',
                'serial' => $acta->equipo->serial ?? ($acta->equipo->numero_inventario ? "INV: {$acta->equipo->numero_inventario}" : ''),
                'devuelto' => $acta->devuelto ? Carbon::parse($acta->devuelto)->format('Y-m-d') : ''
            ];
        }

        if ($acta->perifericos && count($acta->perifericos) > 0) {
            foreach ($acta->perifericos as $periferico) {
                $nombrePerif = optional($periferico->inventario)->nombre ?? "Periférico #{$periferico->inventario_id}";
                $marcaPerif = optional($periferico->inventario)->marca ?? '';
                $modeloPerif = optional($periferico->inventario)->modelo ?? '';
                $serialPerif = optional($periferico->inventario)->serial ?? (optional($periferico->inventario)->codigo ? "COD: {$periferico->inventario->codigo}" : '');

                $items[] = [
                    'es_equipo' => false,
                    'nombre' => $nombrePerif,
                    'cantidad' => $periferico->cantidad ?? 1,
                    'marca' => $marcaPerif,
                    'modelo' => $modeloPerif,
                    'serial' => $serialPerif,
                    'devuelto' => $acta->devuelto ? Carbon::parse($acta->devuelto)->format('Y-m-d') : ''
                ];
            }
        }

        $fecha = Carbon::parse($acta->fecha_entrega ?? now());
        $startRow = 14;
        $maxDefaultSlots = 10;
        $totalItems = count($items);
        $insertedRows = 0;

        // Si hay más de 10 items, insertar filas dinámicamente
        if ($totalItems > $maxDefaultSlots) {
            $extraSlots = $totalItems - $maxDefaultSlots;
            $rowsToInsert = $extraSlots * 2;
            $insertAtRow = 34;
            $sheet->insertNewRowBefore($insertAtRow, $rowsToInsert);
            $insertedRows = $rowsToInsert;

            for ($s = 0; $s < $extraSlots; $s++) {
                $r1 = 34 + ($s * 2);
                $r2 = $r1 + 1;
                $this->mergeAndStyleRowSlot($sheet, $r1, $r2);
            }
        }

        // 4. Escribir cada item avanzando de 2 en 2 filas
        $currentRow = $startRow;
        foreach ($items as $item) {
            $sheet->setCellValue('B' . $currentRow, $fecha->format('Y'));
            $sheet->setCellValue('D' . $currentRow, $fecha->format('m'));
            $sheet->setCellValue('E' . $currentRow, $fecha->format('d'));
            $sheet->setCellValue('F' . $currentRow, $item['nombre']);
            $sheet->setCellValue('O' . $currentRow, $item['cantidad']);
            $sheet->setCellValue('R' . $currentRow, $item['marca']);
            $sheet->setCellValue('V' . $currentRow, $item['modelo']);
            $sheet->setCellValue('Z' . $currentRow, $item['serial']);
            $sheet->setCellValue('AJ' . $currentRow, $item['devuelto']);

            // Insertar firmas
            $this->insertarFirma($sheet, $firmaEntrega, 'AD' . $currentRow, $adminFallback);
            $this->insertarFirma($sheet, $firmaRecibe, 'AG' . $currentRow, $funcionarioFallback);

            $currentRow += 2;
        }

        // 5. Escribir observaciones si existen
        $obsRow = 34 + $insertedRows;
        $obsList = [];
        if ($acta->perifericos) {
            foreach ($acta->perifericos as $p) {
                if (!empty($p->observaciones)) {
                    $nombreItem = optional($p->inventario)->nombre ?? "Periférico #{$p->inventario_id}";
                    $obsList[] = "{$nombreItem}: {$p->observaciones}";
                }
            }
        }
        $obsTexto = 'OBSERVACIONES: ';
        if (!empty($obsList)) {
            $obsTexto .= implode(' | ', $obsList);
        }
        $sheet->setCellValue('B' . $obsRow, $obsTexto);

        $fileName = 'acta_entrega_' . $acta->id . '_' . time() . '.xlsx';
        $exportDir = storage_path('app/public/exports');
        if (!file_exists($exportDir)) {
            mkdir($exportDir, 0777, true);
        }

        $exportPath = $exportDir . '/' . $fileName;
        $writer = new Xlsx($spreadsheet);
        $writer->save($exportPath);
        $spreadsheet->disconnectWorksheets();

        return $fileName;
    }

    private function mergeAndStyleRowSlot($sheet, int $r1, int $r2): void
    {
        $merges = [
            "B{$r1}:C{$r2}",
            "D{$r1}:D{$r2}",
            "E{$r1}:E{$r2}",
            "F{$r1}:N{$r2}",
            "O{$r1}:Q{$r2}",
            "R{$r1}:U{$r2}",
            "V{$r1}:Y{$r2}",
            "Z{$r1}:AC{$r2}",
            "AD{$r1}:AF{$r2}",
            "AG{$r1}:AI{$r2}",
            "AJ{$r1}:AK{$r2}",
        ];

        foreach ($merges as $m) {
            $sheet->mergeCells($m);
        }

        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ]
        ];

        $sheet->getStyle("B{$r1}:AK{$r2}")->applyFromArray($styleArray);
        $sheet->getRowDimension($r1)->setRowHeight(15);
        $sheet->getRowDimension($r2)->setRowHeight(15);
    }

    private function insertarFirma($sheet, $path, $cell, $fallbackPath = null)
    {
        $cleanPath = $path ? ltrim(str_replace(['public/', 'storage/', 'api/'], '', $path), '/') : null;
        if ($cleanPath) {
            $realPath = null;
            if (Storage::disk('public')->exists($cleanPath)) {
                $realPath = storage_path('app/public/' . $cleanPath);
            } elseif (file_exists(public_path('storage/' . $cleanPath))) {
                $realPath = public_path('storage/' . $cleanPath);
            } elseif (file_exists(storage_path('app/public/' . $cleanPath))) {
                $realPath = storage_path('app/public/' . $cleanPath);
            }

            if ($realPath && file_exists($realPath)) {
                $drawing = new Drawing();
                $drawing->setName('Firma');
                $drawing->setDescription('Firma');
                $drawing->setPath($realPath);
                $drawing->setCoordinates($cell);
                $drawing->setHeight(25);
                $drawing->setWorksheet($sheet);
                return;
            }
        }

        if ($fallbackPath) {
            $cleanFallback = ltrim(str_replace(['public/', 'storage/', 'api/'], '', $fallbackPath), '/');
            $realFallbackPath = null;
            if (Storage::disk('public')->exists($cleanFallback)) {
                $realFallbackPath = storage_path('app/public/' . $cleanFallback);
            } elseif (file_exists(public_path('storage/' . $cleanFallback))) {
                $realFallbackPath = public_path('storage/' . $cleanFallback);
            } elseif (file_exists(storage_path('app/public/' . $cleanFallback))) {
                $realFallbackPath = storage_path('app/public/' . $cleanFallback);
            }

            if ($realFallbackPath && file_exists($realFallbackPath)) {
                $drawing = new Drawing();
                $drawing->setName('Firma');
                $drawing->setDescription('Firma');
                $drawing->setPath($realFallbackPath);
                $drawing->setCoordinates($cell);
                $drawing->setHeight(25);
                $drawing->setWorksheet($sheet);
            }
        }
    }
}
