<?php
namespace App\Modules\GestionInfraestructura\Application\UseCases\AgendaMantenimiento;

use App\Models\AgendaMantenimiento;
use Carbon\Carbon;

class ValidarDisponibilidadUseCase
{
    public function execute($fechaInicio, $fechaFin)
    {
        $inicio = Carbon::parse($fechaInicio);
        $fin    = Carbon::parse($fechaFin);

        // Permitir agendamientos del día actual y fechas futuras
        if ($inicio->isBefore(Carbon::today())) {
            return 'La fecha de inicio no puede ser anterior al día de hoy.';
        }

        if ($fin->lessThanOrEqualTo($inicio)) {
            return 'La fecha de fin debe ser posterior a la fecha de inicio.';
        }

        if ($inicio->diffInMinutes($fin) < 15) {
            return 'La duración mínima de un agendamiento es de 15 minutos.';
        }

        if ($inicio->diffInHours($fin) > 24) {
            return 'La duración máxima de un agendamiento es de 24 horas.';
        }

        return null;
    }

    public function isTecnicoDisponible($tecnicoId, $fechaInicio, $fechaFin, $excludeId = null): bool
    {
        $inicio = Carbon::parse($fechaInicio)->utc()->toDateTimeString();
        $fin    = Carbon::parse($fechaFin)->utc()->toDateTimeString();

        $query = AgendaMantenimiento::where('tecnico_id', $tecnicoId)
            ->where(function ($q) use ($inicio, $fin) {
                $q->where('fecha_inicio', '<', $fin)
                  ->where('fecha_fin',    '>', $inicio);
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }

    public function getTecnicosOcupados(string $fechaInicio, string $fechaFin, ?int $excludeId = null): array
    {
        $inicio = Carbon::parse($fechaInicio)->utc()->toDateTimeString();
        $fin    = Carbon::parse($fechaFin)->utc()->toDateTimeString();

        $query = AgendaMantenimiento::select('tecnico_id')
            ->where(function ($q) use ($inicio, $fin) {
                $q->where('fecha_inicio', '<', $fin)
                  ->where('fecha_fin',    '>', $inicio);
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->pluck('tecnico_id')->unique()->values()->toArray();
    }
}