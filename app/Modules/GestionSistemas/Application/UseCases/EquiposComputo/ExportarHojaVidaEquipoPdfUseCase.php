<?php

namespace App\Modules\GestionSistemas\Application\UseCases\EquiposComputo;

use App\Models\PcEquipo;
use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * PDF de la hoja de vida. Se usa en la descarga del módulo y como adjunto
 * al enviar el acta de entrega por correo.
 */
class ExportarHojaVidaEquipoPdfUseCase
{
    public function __construct(
        protected ExcelToPdfConverterInterface $pdfConverter
    ) {}

    public function execute(int $id): string
    {
        $equipo = PcEquipo::with(HojaVidaEquipoPlantilla::RELACIONES)->findOrFail($id);

        $plantilla = new HojaVidaEquipoPlantilla();
        $spreadsheet = $plantilla->construir($equipo, paraPdf: true);

        // LibreOffice convierte el libro completo: dejar solo la hoja de vida
        $activa = $spreadsheet->getActiveSheetIndex();
        for ($i = $spreadsheet->getSheetCount() - 1; $i >= 0; $i--) {
            if ($i !== $activa) {
                $spreadsheet->removeSheetByIndex($i);
            }
        }

        $tmpBase = tempnam(sys_get_temp_dir(), 'hv_excel_');
        $tempExcelPath = $tmpBase . '.xlsx';
        @unlink($tmpBase);

        try {
            (new Xlsx($spreadsheet))->save($tempExcelPath);
            $spreadsheet->disconnectWorksheets();
            $plantilla->limpiarTemporales();

            $pdfContent = $this->pdfConverter->convert($tempExcelPath);

            $exportDir = storage_path('app/public/exports');
            if (!file_exists($exportDir)) {
                mkdir($exportDir, 0777, true);
            }

            $filename = 'hoja_vida_equipo_' . $equipo->id . '_' . time() . '.pdf';
            file_put_contents($exportDir . '/' . $filename, $pdfContent);

            return $filename;
        } finally {
            @unlink($tempExcelPath);
            $plantilla->limpiarTemporales();
        }
    }
}
