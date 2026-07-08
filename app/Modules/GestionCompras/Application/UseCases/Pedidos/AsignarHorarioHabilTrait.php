<?php

namespace App\Modules\GestionCompras\Application\UseCases\Pedidos;

use Carbon\Carbon;

trait AsignarHorarioHabilTrait
{
    /**
     * Calcula y asigna el horario hábil correcto para un pedido programado,
     * ignorando la hora del frontend.
     *
     * @param string $fechaProgramada Fecha enviada por el frontend
     * @return string Fecha y hora válida (Y-m-d H:i:s)
     */
    protected function calcularFechaYHoraHabil(string $fechaProgramada): string
    {
        // Tomar solo la parte de la fecha
        $fecha = Carbon::parse($fechaProgramada)->startOfDay();
        $hoy = Carbon::now();

        // Si la fecha es anterior a hoy, la forzamos a hoy para procesarla en adelante
        if ($fecha->isBefore($hoy->copy()->startOfDay())) {
            $fecha = $hoy->copy()->startOfDay();
        }

        return $this->obtenerSiguienteMomentoHabil($fecha, $fecha->isToday() ? $hoy : null);
    }

    private function obtenerSiguienteMomentoHabil(Carbon $fecha, ?Carbon $horaActualContexto = null): string
    {
        $esHoy = $horaActualContexto !== null;
        
        while (true) {
            $diaSemana = $fecha->dayOfWeek;

            // DOMINGO
            if ($diaSemana === Carbon::SUNDAY) {
                $fecha->addDay();
                $esHoy = false;
                continue;
            }

            // SÁBADO
            if ($diaSemana === Carbon::SATURDAY) {
                if (!$esHoy) {
                    return $fecha->setTime(8, 0, 0)->format('Y-m-d H:i:s');
                } else {
                    $horaActual = $horaActualContexto->format('H:i:s');
                    if ($horaActual < '08:00:00') {
                        return $fecha->setTime(8, 0, 0)->format('Y-m-d H:i:s');
                    } elseif ($horaActual >= '08:00:00' && $horaActual <= '09:00:00') {
                        return $fecha->setTimeFromTimeString($horaActual)->format('Y-m-d H:i:s');
                    } else {
                        $fecha->addDay();
                        $esHoy = false;
                        continue;
                    }
                }
            }

            // LUNES A VIERNES
            if (!$esHoy) {
                return $fecha->setTime(7, 30, 0)->format('Y-m-d H:i:s');
            } else {
                $horaActual = $horaActualContexto->format('H:i:s');
                if ($horaActual < '07:30:00') {
                    return $fecha->setTime(7, 30, 0)->format('Y-m-d H:i:s');
                } elseif ($horaActual >= '07:30:00' && $horaActual <= '08:30:00') {
                    return $fecha->setTimeFromTimeString($horaActual)->format('Y-m-d H:i:s');
                } elseif ($horaActual > '08:30:00' && $horaActual < '14:00:00') {
                    return $fecha->setTime(14, 0, 0)->format('Y-m-d H:i:s');
                } elseif ($horaActual >= '14:00:00' && $horaActual <= '15:00:00') {
                    return $fecha->setTimeFromTimeString($horaActual)->format('Y-m-d H:i:s');
                } else {
                    $fecha->addDay();
                    $esHoy = false;
                    continue;
                }
            }
        }
    }
}
