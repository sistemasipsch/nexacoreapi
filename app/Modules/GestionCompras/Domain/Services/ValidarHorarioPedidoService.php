<?php

namespace App\Modules\GestionCompras\Domain\Services;

use Exception;
use Carbon\Carbon;

class ValidarHorarioPedidoService
{
    /**
     * Valida si la hora actual en Colombia está dentro del horario hábil permitido
     * para realizar pedidos. Si no lo está, lanza una excepción.
     *
     * @throws Exception
     */
    public function validar(): void
    {
        $hoy = Carbon::now('America/Bogota');
        $diaSemana = $hoy->dayOfWeek;
        $horaActual = $hoy->format('H:i:s');
        $esValido = false;

        if ($diaSemana >= Carbon::MONDAY && $diaSemana <= Carbon::FRIDAY) {
            if (($horaActual >= '07:30:00' && $horaActual <= '08:30:00') ||
                ($horaActual >= '14:00:00' && $horaActual <= '15:00:00')) {
                $esValido = true;
            }
        } elseif ($diaSemana === Carbon::SATURDAY) {
            if ($horaActual >= '08:00:00' && $horaActual <= '09:00:00') {
                $esValido = true;
            }
        }

        if (!$esValido) {
            throw new Exception('Los pedidos normales solo se pueden crear en horario hábil: L-V (7:30-8:30 AM y 2:00-3:00 PM), Sábados (8:00-9:00 AM).');
        }
    }
}
