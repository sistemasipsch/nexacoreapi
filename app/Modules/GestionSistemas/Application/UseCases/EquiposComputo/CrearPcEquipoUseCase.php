<?php

namespace App\Modules\GestionSistemas\Application\UseCases\EquiposComputo;

use App\Modules\GestionSistemas\Domain\Contracts\PcEquipoRepositoryInterface;
use App\Models\PcEquipo;
use App\Models\PcMantenimiento;
use App\Models\PcConfigCronograma;
use Carbon\Carbon;

class CrearPcEquipoUseCase
{
    private PcEquipoRepositoryInterface $repository;

    public function __construct(PcEquipoRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(array $data): PcEquipo
    {
        $equipo = $this->repository->create($data);

        // Crear automáticamente el primer mantenimiento preventivo pendiente
        try {
            $config = PcConfigCronograma::first();
            $diasCumplimiento = 180;
            if ($config) {
                if ($config->dias_cumplimiento) {
                    $diasCumplimiento = $config->dias_cumplimiento;
                } elseif ($config->meses_cumplimiento) {
                    $diasCumplimiento = $config->meses_cumplimiento * 30;
                }
            }

            $fechaBase = !empty($equipo->fecha_ingreso) ? Carbon::parse($equipo->fecha_ingreso) : Carbon::now();
            $fechaProgramada = $fechaBase->copy()->addDays($diasCumplimiento);

            PcMantenimiento::create([
                'equipo_id' => $equipo->id,
                'tipo_mantenimiento' => 'preventivo',
                'descripcion' => 'Mantenimiento preventivo programado inicial (primer ciclo desde ingreso)',
                'fecha' => $fechaProgramada->toDateString(),
                'estado' => 'pendiente',
                'creado_por' => auth()->id() ?? 1,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error al programar mantenimiento inicial para equipo #' . $equipo->id . ': ' . $e->getMessage());
        }

        return $equipo;
    }
}
