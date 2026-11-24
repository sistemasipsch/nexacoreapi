<?php

namespace App\Modules\GestionInfraestructura\Application\UseCases\Equipos;

use App\Models\InfraEquipo;
use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Genera en PDF la hoja de vida (PIT-FO-37) o el reporte de mantenimientos (PTSI-FO-04)
 * de un equipo de infraestructura a partir de la plantilla oficial.
 */
class ExportarInfraEquipoPdfUseCase
{
    public const HOJA_VIDA = 'hoja_vida';
    public const MANTENIMIENTOS = 'mantenimientos';

    public function __construct(
        protected ExcelToPdfConverterInterface $pdfConverter
    ) {}

    public function execute(int $id, string $formato = self::HOJA_VIDA): string
    {
        $equipo = InfraEquipo::with(InfraEquipoPlantilla::RELACIONES)->findOrFail($id);

        $plantilla = new InfraEquipoPlantilla();
        $spreadsheet = $formato === self::MANTENIMIENTOS
            ? $plantilla->construirMantenimientos($equipo, paraPdf: true)
            : $plantilla->construirHojaVida($equipo, paraPdf: true);

        $tmpBase = tempnam(sys_get_temp_dir(), 'infra_excel_');
        $tempExcelPath = $tmpBase . '.xlsx';
        @unlink($tmpBase);

        try {
            (new Xlsx($spreadsheet))->save($tempExcelPath);
            $spreadsheet->disconnectWorksheets();

            $pdfContent = $this->pdfConverter->convert($tempExcelPath);

            $exportDir = storage_path('app/public/exports');
            if (!file_exists($exportDir)) {
                mkdir($exportDir, 0777, true);
            }

            $prefijo = $formato === self::MANTENIMIENTOS ? 'mantenimientos_infra_' : 'hoja_vida_infra_';
            $filename = $prefijo . $equipo->id . '_' . time() . '.pdf';
            file_put_contents($exportDir . '/' . $filename, $pdfContent);

            return $filename;
        } finally {
            @unlink($tempExcelPath);
            $plantilla->limpiarTemporales();
        }
    }
}
