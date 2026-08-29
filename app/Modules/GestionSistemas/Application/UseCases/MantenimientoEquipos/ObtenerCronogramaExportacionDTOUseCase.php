<?php

namespace App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos;

use App\Models\PcEquipo;
use App\Models\PcConfigCronograma;
use App\Modules\GestionSistemas\Application\DTOs\Mantenimientos\CronogramaMantenimientoDTO;
use Carbon\Carbon;

class ObtenerCronogramaExportacionDTOUseCase
{
    public function execute(): array
    {
        $equipos = PcEquipo::with([
            'sede', 
            'area', 
            'caracteristicasTecnicas', 
            'mantenimientos' => function($q) {
                $q->orderBy('fecha', 'desc');
            }
        ])->get();

        $configCronograma = PcConfigCronograma::first();
        $diasCumplimiento = 180;
        if ($configCronograma) {
            if ($configCronograma->dias_cumplimiento) {
                $diasCumplimiento = $configCronograma->dias_cumplimiento;
            } elseif ($configCronograma->meses_cumplimiento) {
                $diasCumplimiento = $configCronograma->meses_cumplimiento * 30;
            }
        }
        
        $hoy = Carbon::now();
        $dtos = [];

        foreach ($equipos as $equipo) {
            $mantenimientos = $equipo->mantenimientos;
            $ultimoMantenimiento = $mantenimientos->first();
            
            // Fechas específicas
            $manto2024 = $mantenimientos->filter(function($m) {
                if (!$m->fecha) return false;
                $f = Carbon::parse($m->fecha);
                return $f->year === 2024;
            })->first();

            $manto2025II = $mantenimientos->filter(function($m) {
                if (!$m->fecha) return false;
                $f = Carbon::parse($m->fecha);
                return $f->year === 2025 && $f->month >= 7 && $f->month <= 12;
            })->first();

            $manto2026I = $mantenimientos->filter(function($m) {
                if (!$m->fecha) return false;
                $f = Carbon::parse($m->fecha);
                return $f->year === 2026 && $f->month >= 1 && $f->month <= 6;
            })->first();

            $fechaBase = null;
            if ($ultimoMantenimiento && $ultimoMantenimiento->fecha) {
                $fechaBase = Carbon::parse($ultimoMantenimiento->fecha);
            } elseif ($equipo->fecha_ingreso) {
                $fechaBase = Carbon::parse($equipo->fecha_ingreso);
            }

            $diasStr = 'N/A';
            $vencimiento = 'SIN MANTENIMIENTO';
            $proximaFechaStr = 'N/A';
            $paraCumplimiento = '01 PENDIENTE';
            $diasRestantes = 0;

            if ($fechaBase) {
                $proximaFecha = $fechaBase->copy()->addDays($diasCumplimiento);
                $proximaFechaStr = $proximaFecha->format('Y-m-d');
                
                $diasDiff = $fechaBase->diffInDays($hoy);
                $diasStr = (string)$diasDiff;
                
                $diasRestantes = $hoy->diffInDays($proximaFecha, false); // positivo si proximaFecha es en el futuro

                if ($hoy->gt($proximaFecha)) {
                    $vencimiento = 'VENCIDO';
                    $paraCumplimiento = '01 PENDIENTE';
                } elseif ($proximaFecha->copy()->subDays(30)->lte($hoy)) {
                    $vencimiento = 'POR VENCER';
                    $paraCumplimiento = '01 PENDIENTE';
                } else {
                    $vencimiento = 'AL DÍA';
                    $paraCumplimiento = $ultimoMantenimiento ? '03 REALIZADO' : '02 NUEVO NO APLICA';
                }
            } else {
                // Sin fecha de ingreso ni mantenimientos
                $paraCumplimiento = '01 PENDIENTE';
            }

            $estadoMantenimiento = $ultimoMantenimiento ? ($ultimoMantenimiento->estado ?? 'pendiente') : 'sin_registro';

            $dtos[] = new CronogramaMantenimientoDTO(
                equipoComputo: $equipo->nombre_equipo ?? '',
                marca: $equipo->marca ?? '',
                modelo: $equipo->modelo ?? '',
                sede: optional($equipo->sede)->nombre ?? '',
                area: optional($equipo->area)->nombre ?? '',
                serial: $equipo->serial ?? '',
                propioArriendo: $equipo->propiedad === 'empresa' ? 'PROPIO' : ($equipo->propiedad === 'empleado' ? 'ARRIENDO' : mb_strtoupper($equipo->propiedad ?? '')),
                ipFijaLocal: $equipo->ip_fija ?? '',
                numeroInventario: $equipo->numero_inventario ?? '',
                procesador: optional($equipo->caracteristicasTecnicas)->procesador ?? '',
                memoriaRam: optional($equipo->caracteristicasTecnicas)->memoria_ram ?? '',
                paraCumplimiento: $paraCumplimiento,
                fechaUltimoMantenimiento2024: $manto2024 ? Carbon::parse($manto2024->fecha)->format('Y-m-d') : 'N/A',
                hoy: $hoy->format('Y-m-d'),
                dias: $diasStr,
                vencimiento: $vencimiento,
                fechaProgramada: $proximaFechaStr,
                ejecucion: $ultimoMantenimiento ? 'SI' : 'NO',
                fechaUltimoMantenimiento2025IISemestre: $manto2025II ? Carbon::parse($manto2025II->fecha)->format('Y-m-d') : 'N/A',
                fechaUltimoMantenimiento2026ISemestre: $manto2026I ? Carbon::parse($manto2026I->fecha)->format('Y-m-d') : 'N/A',
                estadoMantenimiento: mb_strtoupper($estadoMantenimiento)
            );
        }

        return $dtos;
    }
}
