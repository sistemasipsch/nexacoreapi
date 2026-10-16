<?php

namespace App\Modules\GestionInfraestructura\Application\UseCases\Equipos;

use App\Models\InfraEquipo;
use Carbon\Carbon;

class RegistrarTrasladoUseCase
{
    public function execute(int $id, array $datosTraslado): InfraEquipo
    {
        $equipo = InfraEquipo::findOrFail($id);

        $sedeAnterior = optional($equipo->sede)->nombre ?? 'Sede previa';
        $areaAnterior = optional($equipo->area)->nombre ?? 'Área previa';

        $fechaTraslado = !empty($datosTraslado['fecha_traslado'])
            ? Carbon::parse($datosTraslado['fecha_traslado'])->toDateString()
            : Carbon::now()->toDateString();

        $fechaFormateada = Carbon::parse($fechaTraslado)->format('d/m/Y');
        $motivoTexto = $datosTraslado['motivo_observacion'] ?? $datosTraslado['motivo_traslado'] ?? '';
        $motivo = !empty($motivoTexto) ? " - Motivo: {$motivoTexto}" : '';
        $nuevaNota = "[Traslado {$fechaFormateada} desde {$sedeAnterior} ({$areaAnterior}){$motivo}]";

        $observacionesActualizadas = trim(($equipo->observaciones ?? '') . "\n" . $nuevaNota);

        $nuevaAreaId = $datosTraslado['area_id'] ?? $datosTraslado['traslado_area_id'] ?? $equipo->area_id;
        $nuevoResponsableId = $datosTraslado['responsable_id'] ?? $datosTraslado['custodio_id'] ?? $equipo->responsable_id;

        $equipo->update([
            'traslado_dependencia_id' => $equipo->sede_id,
            'traslado_area_id' => $equipo->area_id,
            'sede_id' => $datosTraslado['sede_id'],
            'area_id' => $nuevaAreaId,
            'ubicacion_especifica' => $datosTraslado['ubicacion_especifica'] ?? $equipo->ubicacion_especifica,
            'responsable_id' => $nuevoResponsableId,
            'fecha_traslado' => $fechaTraslado,
            'observaciones' => $observacionesActualizadas,
        ]);

        return $equipo->fresh([
            'sede',
            'area',
            'responsable.cargo',
            'proveedor',
            'trasladoDependencia',
            'trasladoArea',
            'creador',
            'mantenimientos',
        ]);
    }
}
