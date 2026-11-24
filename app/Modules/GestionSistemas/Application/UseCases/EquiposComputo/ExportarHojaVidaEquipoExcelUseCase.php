<?php

namespace App\Modules\GestionSistemas\Application\UseCases\EquiposComputo;

use App\Models\PcEquipo;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Exception;

class ExportarHojaVidaEquipoExcelUseCase
{
    public function execute(int $id): string
    {
        $equipo = PcEquipo::with(HojaVidaEquipoPlantilla::RELACIONES)->find($id);

        if (!$equipo) {
            throw new Exception('Equipo de cómputo no encontrado', 404);
        }

        $plantilla = new HojaVidaEquipoPlantilla();
        $spreadsheet = $plantilla->construir($equipo);

        $exportDir = storage_path('app/public/exports');
        if (!file_exists($exportDir)) {
            mkdir($exportDir, 0777, true);
        }

        $fileName = 'hoja_vida_equipo_' . $equipo->id . '_' . time() . '.xlsx';

        try {
            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($exportDir . '/' . $fileName);
        } finally {
            $spreadsheet->disconnectWorksheets();
            $plantilla->limpiarTemporales();
        }

        return $fileName;
    }
}
