<?php

namespace App\Modules\GestionSistemas\Application\UseCases\ActasDevolucion;

use App\Models\PcDevuelto;
use App\Models\Usuario;
use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
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
            'entrega.equipo.sede',
            'entrega.equipo.area',
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

        // 1. Datos de la IPS y Datos del Funcionario
        $sheet->setCellValue('B7', 'NOMBRE: IPS CLINICAL HOUSE');
        $sheet->setCellValue('B8', 'NIT: 900752620');
        $sheet->setCellValue('B9', 'DIRECCION: AV 1E #11-152 QUINTA VELEZ');
        $sheet->setCellValue('B10', 'TELEFONO: 5956636');

        $nombre = optional($entrega?->funcionario)->nombre ?? '';
        $cedula = optional($entrega?->funcionario)->cedula ?? '';
        $cargo = optional(optional($entrega?->funcionario)->cargo)->nombre ?? (is_string($entrega?->funcionario?->cargo) ? $entrega->funcionario->cargo : '');
        $telefono = optional($entrega?->funcionario)->telefono ?? '';
        $proceso = optional(optional($entrega?->equipo)->area)->nombre ?? '';

        $sheet->setCellValue('V7', 'NOMBRE: ' . $nombre);
        $sheet->setCellValue('V8', 'NUMERO DE IDENTIFICACION: ' . $cedula);
        $sheet->setCellValue('V9', 'CARGO: ' . $cargo);
        $sheet->setCellValue('V10', 'TELEFONO: ' . $telefono);
        $sheet->setCellValue('V11', 'PROCESO: ' . $proceso);

        // 2. Firmas disponibles con fallback
        // En devolución: AD es Quien Devuelve (Funcionario), AG es Quien Recibe (Sistemas/Admin)
        $firmaEntrega = $devolucion->getRawOriginal('firma_entrega') ?? $devolucion->attributes['firma_entrega'] ?? null;
        $funcionarioFallback = optional($entrega?->funcionario)->getRawOriginal('firma') ?? optional($entrega?->funcionario)->firma ?? null;

        $firmaRecibe = $devolucion->getRawOriginal('firma_recibe') ?? $devolucion->attributes['firma_recibe'] ?? null;
        $adminFallback = null;
        $admin = Usuario::whereNotNull('firma_digital')->where('firma_digital', '!=', '')->first();
        if ($admin) {
            $adminFallback = $admin->getRawOriginal('firma_digital');
        }

        // 3. Preparar lista de items (Equipo Principal + Todos los Periféricos y Accesorios)
        $items = [];
        if ($entrega && $entrega->equipo) {
            $items[] = [
                'es_equipo' => true,
                'nombre' => $entrega->equipo->nombre_equipo ?? 'Equipo PC',
                'cantidad' => 1,
                'marca' => $entrega->equipo->marca ?? '',
                'modelo' => $entrega->equipo->modelo ?? '',
                'serial' => $entrega->equipo->serial ?? ($entrega->equipo->numero_inventario ? "INV: {$entrega->equipo->numero_inventario}" : ''),
                'estado' => $entrega->equipo->estado ?? 'DEVUELTO'
            ];
        }

        if ($entrega && $entrega->perifericos && count($entrega->perifericos) > 0) {
            foreach ($entrega->perifericos as $periferico) {
                $nombrePerif = optional($periferico->inventario)->nombre ?? "Periférico #{$periferico->inventario_id}";
                $marcaPerif = optional($periferico->inventario)->marca ?? '';
                $modeloPerif = optional($periferico->inventario)->modelo ?? '';
                $serialPerif = optional($periferico->inventario)->serial ?? (optional($periferico->inventario)->codigo ? "COD: {$periferico->inventario->codigo}" : '');
                $estadoPerif = !empty($periferico->observaciones) ? $periferico->observaciones : 'DEVUELTO';

                $items[] = [
                    'es_equipo' => false,
                    'nombre' => $nombrePerif,
                    'cantidad' => $periferico->cantidad ?? 1,
                    'marca' => $marcaPerif,
                    'modelo' => $modeloPerif,
                    'serial' => $serialPerif,
                    'estado' => $estadoPerif
                ];
            }
        }

        $fecha = Carbon::parse($devolucion->fecha_devolucion ?? now());
        $startRow = 14;
        $maxDefaultSlots = 11; // Filas 14 a 35 (11 slots de 2 filas cada uno)
        $totalItems = count($items);
        $insertedRows = 0;

        // Si hay más de 11 items, insertar filas dinámicamente antes de row 36
        if ($totalItems > $maxDefaultSlots) {
            $extraSlots = $totalItems - $maxDefaultSlots;
            $rowsToInsert = $extraSlots * 2;
            $insertAtRow = 36;
            $sheet->insertNewRowBefore($insertAtRow, $rowsToInsert);
            $insertedRows = $rowsToInsert;

            // Formatear y combinar celdas para los slots adicionales
            for ($s = 0; $s < $extraSlots; $s++) {
                $r1 = 36 + ($s * 2);
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
            $sheet->setCellValue('AJ' . $currentRow, $item['estado']);

            // Insertar firmas centradas en cada fila correspondiente
            $this->insertarFirma($sheet, $firmaEntrega, 'AD' . $currentRow, $funcionarioFallback);
            $this->insertarFirma($sheet, $firmaRecibe, 'AG' . $currentRow, $adminFallback);

            // Ajustar estilos y ajuste de texto para evitar cortes
            $r2 = $currentRow + 1;
            $sheet->getStyle("B{$currentRow}:E{$r2}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$currentRow}:N{$r2}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)->setWrapText(true);
            $sheet->getStyle("O{$currentRow}:Q{$r2}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("R{$currentRow}:U{$r2}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setWrapText(true);
            $sheet->getStyle("V{$currentRow}:Y{$r2}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setWrapText(true);
            $sheet->getStyle("Z{$currentRow}:AC{$r2}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setWrapText(true);
            $sheet->getStyle("AJ{$currentRow}:AL{$r2}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setWrapText(true);

            $sheet->getStyle("B{$currentRow}:AL{$r2}")->getFont()->setSize(8.5);

            $currentRow += 2; // Avanzar al siguiente slot de 2 filas
        }

        // 5. Escribir observaciones si existen
        $obsRow = 36 + $insertedRows;
        $obsList = [];
        if (!empty($devolucion->observaciones)) {
            $obsList[] = $devolucion->observaciones;
        }
        if ($entrega && $entrega->perifericos) {
            foreach ($entrega->perifericos as $p) {
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
        $sheet->getStyle('B' . $obsRow)->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('B' . $obsRow)->getFont()->setSize(8);

        // Estilo cabecera funcionario
        $sheet->getStyle('V7:AL11')->getFont()->setSize(8.5);
        $sheet->getStyle('B7:U10')->getFont()->setSize(8.5);

        // Configuración de página y márgenes para PDF limpio
        $sheet->getPageMargins()->setTop(0.3);
        $sheet->getPageMargins()->setBottom(0.3);
        $sheet->getPageMargins()->setLeft(0.3);
        $sheet->getPageMargins()->setRight(0.3);

        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight($totalItems > 11 ? 0 : 1);

        // Remover otras hojas para evitar que LibreOffice genere páginas extra en el PDF
        while ($spreadsheet->getSheetCount() > 1) {
            $activeIndex = $spreadsheet->getActiveSheetIndex();
            $indexToRemove = $activeIndex === 0 ? 1 : 0;
            $spreadsheet->removeSheetByIndex($indexToRemove);
        }

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
            "AJ{$r1}:AL{$r2}",
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

        $sheet->getStyle("B{$r1}:AL{$r2}")->applyFromArray($styleArray);
        $sheet->getRowDimension($r1)->setRowHeight(15);
        $sheet->getRowDimension($r2)->setRowHeight(15);
    }

    private function insertarFirma($sheet, $path, $cell, $fallbackPath = null)
    {
        $realPath = $this->resolveImagePath($path) ?? $this->resolveImagePath($fallbackPath);

        if ($realPath && file_exists($realPath)) {
            $drawing = new Drawing();
            $drawing->setName('Firma');
            $drawing->setDescription('Firma');
            $drawing->setPath($realPath);
            $drawing->setCoordinates($cell);
            $drawing->setHeight(25);
            $drawing->setOffsetX(15);
            $drawing->setOffsetY(4);
            $drawing->setWorksheet($sheet);
        }
    }

    private function resolveImagePath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        // Base64 Data URI
        if (str_starts_with($path, 'data:image')) {
            try {
                if (preg_match('/^data:image\/(\w+);base64,/', $path, $type)) {
                    $data = substr($path, strpos($path, ',') + 1);
                    $decoded = base64_decode($data);
                    if ($decoded !== false) {
                        $tempPath = tempnam(sys_get_temp_dir(), 'sig_') . '.' . strtolower($type[1]);
                        file_put_contents($tempPath, $decoded);
                        return $tempPath;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore base64 error
            }
            return null;
        }

        // Full URL or relative path
        $cleanPath = $path;
        if (preg_match('#/storage/(.+)#', $cleanPath, $matches)) {
            $cleanPath = $matches[1];
        }
        $cleanPath = ltrim(str_replace(['public/', 'storage/', 'api/'], '', $cleanPath), '/');

        if (Storage::disk('public')->exists($cleanPath)) {
            return storage_path('app/public/' . $cleanPath);
        } elseif (file_exists(public_path('storage/' . $cleanPath))) {
            return public_path('storage/' . $cleanPath);
        } elseif (file_exists(storage_path('app/public/' . $cleanPath))) {
            return storage_path('app/public/' . $cleanPath);
        } elseif (file_exists(storage_path('app/' . $cleanPath))) {
            return storage_path('app/' . $cleanPath);
        }

        return null;
    }
}



