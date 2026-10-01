<?php

namespace App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos;

use App\Models\PcEquipo;
use App\Models\PcConfigCronograma;
use App\Modules\GestionSistemas\Application\DTOs\Mantenimientos\CronogramaMantenimientoDTO;
use Carbon\Carbon;

class ObtenerCronogramaExportacionDTOUseCase
{
    public function execute(?int $sedeId = null, ?string $tipoMantenimiento = null): array
    {
        $query = PcEquipo::with([
            'sede', 
            'area', 
            'caracteristicasTecnicas', 
            'mantenimientos' => function($q) use ($tipoMantenimiento) {
                if ($tipoMantenimiento && !in_array($tipoMantenimiento, ['all', 'todos', 'todas', ''], true)) {
                    $q->where('tipo_mantenimiento', $tipoMantenimiento);
                }
                $q->orderBy('fecha', 'desc');
            }
        ]);

        if ($sedeId) {
            $query->where('sede_id', $sedeId);
        }

        if ($tipoMantenimiento && !in_array($tipoMantenimiento, ['all', 'todos', 'todas', ''], true)) {
            $query->whereHas('mantenimientos', function($q) use ($tipoMantenimiento) {
                $q->where('tipo_mantenimiento', $tipoMantenimiento);
            });
        }

        $equipos = $query->get();

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
            $mantenimientosCompletados = $mantenimientos->where('estado', 'completado');
            $ultimoMantenimientoCompletado = $mantenimientosCompletados->sortByDesc('fecha')->first();
            $proximoMantenimientoPendiente = $mantenimientos->where('estado', 'pendiente')->sortBy('fecha')->first();
            
            // 2024: Último mantenimiento realizado en 2024
            $manto2024 = $mantenimientosCompletados->filter(function($m) {
                if (!$m->fecha) return false;
                return Carbon::parse($m->fecha)->year === 2024;
            })->sortByDesc('fecha')->first();

            // 2025: Último mantenimiento realizado en 2025
            $manto2025 = $mantenimientosCompletados->filter(function($m) {
                if (!$m->fecha) return false;
                return Carbon::parse($m->fecha)->year === 2025;
            })->sortByDesc('fecha')->first();

            // 2026: PRIMER mantenimiento realizado en 2026 (el más temprano de este año)
            $manto2026 = $mantenimientosCompletados->filter(function($m) {
                if (!$m->fecha) return false;
                return Carbon::parse($m->fecha)->year === 2026;
            })->sortBy('fecha')->first();

            $fecha2024Str = $manto2024 ? Carbon::parse($manto2024->fecha)->format('Y-m-d') : 'N/A';
            $fecha2025Str = $manto2025 ? Carbon::parse($manto2025->fecha)->format('Y-m-d') : ($equipo->fecha_ingreso && Carbon::parse($equipo->fecha_ingreso)->year === 2025 ? Carbon::parse($equipo->fecha_ingreso)->format('Y-m-d') : 'N/A');
            $fecha2026Str = $manto2026 ? Carbon::parse($manto2026->fecha)->format('Y-m-d') : 'N/A';

            $fechaBase = null;
            if ($ultimoMantenimientoCompletado && $ultimoMantenimientoCompletado->fecha) {
                $fechaBase = Carbon::parse($ultimoMantenimientoCompletado->fecha);
            } elseif ($equipo->fecha_ingreso) {
                $fechaBase = Carbon::parse($equipo->fecha_ingreso);
            }

            $diasStr = 'N/A';
            $vencimiento = 'SIN MANTENIMIENTO';
            $proximaFechaStr = 'N/A';
            $paraCumplimiento = '01 PROXIMOS A VENCERSE';
            $diasRestantes = 0;

            if ($fechaBase) {
                $proximaFecha = $proximoMantenimientoPendiente && $proximoMantenimientoPendiente->fecha 
                    ? Carbon::parse($proximoMantenimientoPendiente->fecha) 
                    : $fechaBase->copy()->addDays($diasCumplimiento);
                $proximaFechaStr = $proximaFecha->format('Y-m-d');
                
                $diasDiff = $fechaBase->diffInDays($hoy);
                $diasStr = (string)$diasDiff;
                
                $diasRestantes = $hoy->diffInDays($proximaFecha, false); // positivo si proximaFecha es en el futuro

                if ($hoy->gt($proximaFecha)) {
                    $vencimiento = 'VENCIDO';
                    $paraCumplimiento = '01 PROXIMOS A VENCERSE';
                } elseif ($proximaFecha->copy()->subDays(30)->lte($hoy)) {
                    $vencimiento = 'POR VENCER';
                    $paraCumplimiento = '01 PROXIMOS A VENCERSE';
                } else {
                    $vencimiento = 'AL DÍA';
                    $paraCumplimiento = $ultimoMantenimientoCompletado ? '03 REALIZADO' : '01 PROXIMOS A VENCERSE';
                }
            } else {
                // Sin fecha de ingreso ni mantenimientos
                $paraCumplimiento = '01 PROXIMOS A VENCERSE';
            }

            $estadoMantenimiento = $ultimoMantenimientoCompletado ? 'COMPLETADO' : ($proximoMantenimientoPendiente ? 'PENDIENTE' : 'SIN_REGISTRO');

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
                fechaUltimoMantenimiento2024: $fecha2024Str,
                hoy: $hoy->format('Y-m-d'),
                dias: $diasStr,
                vencimiento: $vencimiento,
                fechaProgramada: $proximaFechaStr,
                ejecucion: $ultimoMantenimientoCompletado ? 'SI' : 'NO',
                fechaUltimoMantenimiento2025IISemestre: $fecha2025Str,
                fechaUltimoMantenimiento2026ISemestre: $fecha2026Str,
                estadoMantenimiento: $estadoMantenimiento
            );
        }

        return $dtos;
    }
}
