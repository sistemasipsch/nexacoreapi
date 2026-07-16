<?php

namespace App\Modules\GestionCompras\Application\UseCases\Pedidos;

use Carbon\Carbon;

trait AsignarHorarioHabilTrait
{
    /**
     * Zona horaria oficial de la aplicación (Colombia).
     */
    private const TIMEZONE = 'America/Bogota';

    /**
     * Calcula y asigna el horario hábil correcto para un pedido programado,
     * ignorando la hora del frontend y usando siempre la hora de Colombia.
     *
     * Horarios hábiles:
     *  - Lunes a Viernes: 7:30 AM – 8:30 AM  |  2:00 PM – 3:00 PM
     *  - Sábados:         8:00 AM – 9:00 AM
     *
     * @param string $fechaProgramada Fecha enviada por el frontend
     * @return string Fecha y hora válida en hora Colombia (Y-m-d H:i:s)
     */
    protected function calcularFechaYHoraHabil(string $fechaProgramada): string
    {
        // Trabajamos siempre en hora Colombia para evitar desfases UTC
        $fecha = Carbon::parse($fechaProgramada, self::TIMEZONE)->startOfDay();
        $hoy   = Carbon::now(self::TIMEZONE);

        // Si la fecha es anterior a hoy, la forzamos a hoy
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

            // DOMINGO → avanzar al lunes
            if ($diaSemana === Carbon::SUNDAY) {
                $fecha->addDay();
                $esHoy = false;
                continue;
            }

            // SÁBADO → solo 8:00 AM – 9:00 AM
            if ($diaSemana === Carbon::SATURDAY) {
                if (!$esHoy) {
                    return $fecha->setTime(8, 0, 0)->format('Y-m-d H:i:s');
                }

                $horaActual = $horaActualContexto->format('H:i:s');

                if ($horaActual < '08:00:00') {
                    // Antes de ventana → programar al inicio
                    return $fecha->setTime(8, 0, 0)->format('Y-m-d H:i:s');
                } elseif ($horaActual >= '08:00:00' && $horaActual <= '09:00:00') {
                    // Dentro de la ventana → usar hora actual
                    return $fecha->setTimeFromTimeString($horaActual)->format('Y-m-d H:i:s');
                } else {
                    // Pasó la ventana del sábado → ir al lunes
                    $fecha->addDay(); // domingo
                    $fecha->addDay(); // lunes
                    $esHoy = false;
                    continue;
                }
            }

            // LUNES A VIERNES → 7:30-8:30 AM  |  2:00-3:00 PM
            if (!$esHoy) {
                return $fecha->setTime(7, 30, 0)->format('Y-m-d H:i:s');
            }

            $horaActual = $horaActualContexto->format('H:i:s');

            if ($horaActual < '07:30:00') {
                // Antes de la primera ventana
                return $fecha->setTime(7, 30, 0)->format('Y-m-d H:i:s');
            } elseif ($horaActual >= '07:30:00' && $horaActual <= '08:30:00') {
                // Dentro de la primera ventana
                return $fecha->setTimeFromTimeString($horaActual)->format('Y-m-d H:i:s');
            } elseif ($horaActual > '08:30:00' && $horaActual < '14:00:00') {
                // Entre ventanas → próxima ventana de la tarde
                return $fecha->setTime(14, 0, 0)->format('Y-m-d H:i:s');
            } elseif ($horaActual >= '14:00:00' && $horaActual <= '15:00:00') {
                // Dentro de la segunda ventana
                return $fecha->setTimeFromTimeString($horaActual)->format('Y-m-d H:i:s');
            } else {
                // Pasó ambas ventanas → siguiente día hábil
                $fecha->addDay();
                $esHoy = false;
                continue;
            }
        }
    }
}
