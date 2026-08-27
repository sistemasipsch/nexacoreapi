<?php

namespace App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos;

use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Exception;
use App\Models\PcMantenimiento;

class ExportarMantenimientoEquipoPdfUseCase
{
    public function __construct(
        protected ExcelToPdfConverterInterface $pdfConverter
    ) {}

    public function execute(int $id): string
    {
        $mantenimiento = PcMantenimiento::with([
            'equipo.sede',
            'equipo.area',
            'equipo.responsable',
            'equipo.caracteristicasTecnicas',
            'empresaResponsable',
            'creador:id,nombre_completo'
        ])->findOrFail($id);

        $templatePath = storage_path('app/templates/plantilla_mantenimiento_equipo.xlsx');
        
        if (!file_exists($templatePath)) {
            throw new Exception('No se encontró la plantilla de mantenimiento de equipo.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Datos del equipo y empresa
        $empresaNombre = optional($mantenimiento->empresaResponsable)->nombre ?? '';
        $equipoNombre = optional($mantenimiento->equipo)->nombre_equipo ?? '';
        $marca = optional($mantenimiento->equipo)->marca ?? '';
        $modelo = optional($mantenimiento->equipo)->modelo ?? '';
        $serial = optional($mantenimiento->equipo)->serial ?? '';
        
        $areaNombre = optional(optional($mantenimiento->equipo)->area)->nombre ?? '';
        $sedeNombre = optional(optional($mantenimiento->equipo)->sede)->nombre ?? '';

        // Escribir manteniendo el prefijo de las celdas activas
        $sheet->setCellValue('B6', $sheet->getCell('B6')->getValue() . $empresaNombre);
        $sheet->setCellValue('B7', $sheet->getCell('B7')->getValue() . $equipoNombre);
        $sheet->setCellValue('K7', $sheet->getCell('K7')->getValue() . $marca);
        $sheet->setCellValue('U7', $sheet->getCell('U7')->getValue() . $modelo);
        $sheet->setCellValue('AD7', $sheet->getCell('AD7')->getValue() . $serial);
        $sheet->setCellValue('B8', $sheet->getCell('B8')->getValue() . trim($areaNombre . ' - ' . $sedeNombre, ' - '));

        // Insertar datos de mantenimiento
        if ($mantenimiento->fecha) {
            $fecha = Carbon::parse($mantenimiento->fecha);
            $sheet->setCellValue('B11', $fecha->format('Y'));
            $sheet->setCellValue('D11', $fecha->format('m'));
            $sheet->setCellValue('E11', $fecha->format('d'));
        }

        $sheet->setCellValue('F11', $mantenimiento->cpu ? 'X' : '');
        $sheet->setCellValue('G11', $mantenimiento->pantalla ? 'X' : '');
        $sheet->setCellValue('H11', $mantenimiento->teclado ? 'X' : '');
        $sheet->setCellValue('I11', $mantenimiento->mouse ? 'X' : '');
        $sheet->setCellValue('J11', $mantenimiento->unidad_cd ? 'X' : '');

        $sheet->setCellValue('K11', $mantenimiento->tipo_mantenimiento === 'preventivo' ? 'X' : '');
        $sheet->setCellValue('M11', $mantenimiento->tipo_mantenimiento === 'correctivo' ? 'X' : '');

        $sheet->setCellValue('O11', $mantenimiento->descripcion ?? '');

        $sheet->setCellValue('W11', $mantenimiento->repuesto ? 'X' : '');
        $sheet->setCellValue('X11', $mantenimiento->repuesto ? '' : 'X');

        $sheet->setCellValue('Y11', $mantenimiento->cantidad_repuesto ?? '');
        $sheet->setCellValue('Z11', $mantenimiento->costo_repuesto ?? '');
        $sheet->setCellValue('AB11', $mantenimiento->nombre_repuesto ?? '');

        // Insertar Firmas si existen
        $this->insertarFirma($sheet, $mantenimiento->firma_personal_cargo, 'AG11');
        $this->insertarFirma($sheet, $mantenimiento->firma_sistemas, 'AJ11');

        // Las firmas ya fueron insertadas.
        // NOTA: No eliminamos hojas adicionales aquí para evitar que LibreOffice falle por referencias corruptas.

        $filename = 'mantenimiento_equipo_' . $mantenimiento->id . '_' . time() . '.pdf';

        $tempExcelPath = tempnam(sys_get_temp_dir(), 'mantenimiento_excel_') . '.xlsx';
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
            $drawing->setHeight(30); // Ajustar según el alto de la fila/celda en la plantilla
            $drawing->setWorksheet($sheet);
        }
    }

    private function sanitize(string $string): string
    {
        $string = preg_replace('/[^A-Za-z0-9\-\s]/', '', $string);
        return trim(preg_replace('/\s+/', '_', $string));
    }
}
