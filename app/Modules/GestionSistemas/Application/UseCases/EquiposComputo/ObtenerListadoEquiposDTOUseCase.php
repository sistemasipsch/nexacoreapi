<?php

namespace App\Modules\GestionSistemas\Application\UseCases\EquiposComputo;

use App\Models\PcEquipo;
use App\Models\PcConfigCronograma;
use App\Modules\GestionSistemas\Application\DTOs\ListadoEquipoDTO;
use Carbon\Carbon;

class ObtenerListadoEquiposDTOUseCase
{
    public function execute(): array
    {
        $equipos = PcEquipo::with([
            'sede', 
            'area', 
            'responsable', 
            'caracteristicasTecnicas', 
            'licenciasSoftware', 
            'mantenimientos' => function($q) {
                $q->orderBy('fecha', 'desc');
            },
            'entregas' => function($q) {
                $q->orderBy('fecha_entrega', 'desc')->orderBy('id', 'desc')->with('funcionario');
            }
        ])->get();

        $configCronograma = PcConfigCronograma::first();
        // The prompt said: (esta tabla es donde tenemos configurado el tema de cada cuanto dia se hace mantenimiento el equipo, y solo por el momento se esta usando en dias)
        $diasCumplimiento = $configCronograma ? $configCronograma->dias_cumplimiento : 180;
        
        $hoy = Carbon::now();
        $dtos = [];

        foreach ($equipos as $equipo) {
            $ultimoMantenimiento = $equipo->mantenimientos->first();
            
            $fechaUltimoMantenimientoStr = 'N/A';
            $diasStr = 'N/A';
            $vencimiento = 'SIN MANTENIMIENTO';
            $proximaFechaStr = 'N/A';

            if ($ultimoMantenimiento && $ultimoMantenimiento->fecha) {
                $fechaUltimoMantenimiento = Carbon::parse($ultimoMantenimiento->fecha);
                $fechaUltimoMantenimientoStr = $fechaUltimoMantenimiento->format('Y-m-d');
                
                $proximaFecha = $fechaUltimoMantenimiento->copy()->addDays($diasCumplimiento);
                $proximaFechaStr = $proximaFecha->format('Y-m-d');
                
                $diasDiff = $fechaUltimoMantenimiento->diffInDays($hoy);
                $diasStr = (string)$diasDiff;

                if ($hoy->gt($proximaFecha)) {
                    $vencimiento = 'VENCIDO';
                } elseif ($proximaFecha->copy()->subDays(15)->lte($hoy)) {
                    $vencimiento = 'POR VENCER';
                } else {
                    $vencimiento = 'AL DÍA';
                }
            }

            $ultimaEntrega = $equipo->entregas->first();
            $personalEncargado = '';
            if ($ultimaEntrega && $ultimaEntrega->funcionario) {
                $personalEncargado = $ultimaEntrega->funcionario->nombre ?? '';
            } else {
                $personalEncargado = optional($equipo->responsable)->nombre ?? optional($equipo->responsable)->nombre_completo ?? '';
            }

            $dtos[] = new ListadoEquipoDTO(
                equipoComputo: $equipo->nombre_equipo ?? '',
                marca: $equipo->marca ?? '',
                modelo: $equipo->modelo ?? '',
                area: optional($equipo->area)->nombre ?? '',
                personalEncargado: $personalEncargado,
                serial: $equipo->serial ?? '',
                propiedadEmpleado: $equipo->propiedad ?? '',
                ipFijaLocal: $equipo->ip_fija ?? '',
                numeroInventario: $equipo->numero_inventario ?? '',
                sede: optional($equipo->sede)->nombre ?? '',
                procesador: optional($equipo->caracteristicasTecnicas)->procesador ?? '',
                memoriaRam: optional($equipo->caracteristicasTecnicas)->memoria_ram ?? '',
                windows: optional($equipo->licenciasSoftware)->windows ?? '',
                office: optional($equipo->licenciasSoftware)->office ?? '',
                nitro: optional($equipo->licenciasSoftware)->nitro ?? '',
                paraCumplimiento: (string)$diasCumplimiento,
                fechaUltimoMantenimiento: $fechaUltimoMantenimientoStr,
                hoy: $hoy->format('Y-m-d'),
                dias: $diasStr,
                vencimiento: $vencimiento,
                proximaFecha: $proximaFechaStr,
                estadoProgramacion: optional($ultimoMantenimiento)->estado ?? 'SIN PROGRAMAR'
            );
        }

        return $dtos;
    }
}
