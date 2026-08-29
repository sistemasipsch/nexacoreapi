<?php

namespace App\Modules\GestionSistemas\Application\DTOs\Mantenimientos;

class CronogramaMantenimientoDTO
{
    public function __construct(
        public readonly string $equipoComputo,
        public readonly string $marca,
        public readonly string $modelo,
        public readonly string $sede,
        public readonly string $area,
        public readonly string $serial,
        public readonly string $propioArriendo,
        public readonly string $ipFijaLocal,
        public readonly string $numeroInventario,
        public readonly string $procesador,
        public readonly string $memoriaRam,
        public readonly string $paraCumplimiento,
        public readonly string $fechaUltimoMantenimiento2024,
        public readonly string $hoy,
        public readonly string $dias,
        public readonly string $vencimiento,
        public readonly string $fechaProgramada,
        public readonly string $ejecucion,
        public readonly string $fechaUltimoMantenimiento2025IISemestre,
        public readonly string $fechaUltimoMantenimiento2026ISemestre,
        public readonly string $estadoMantenimiento
    ) {}
}
