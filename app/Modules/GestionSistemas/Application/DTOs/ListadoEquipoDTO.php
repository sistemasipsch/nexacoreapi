<?php

namespace App\Modules\GestionSistemas\Application\DTOs;

class ListadoEquipoDTO
{
    public function __construct(
        public readonly string $equipoComputo,
        public readonly string $marca,
        public readonly string $modelo,
        public readonly string $area,
        public readonly string $personalEncargado,
        public readonly string $serial,
        public readonly string $propiedadEmpleado,
        public readonly string $ipFijaLocal,
        public readonly string $numeroInventario,
        public readonly string $sede,
        public readonly string $procesador,
        public readonly string $memoriaRam,
        public readonly string $windows,
        public readonly string $office,
        public readonly string $nitro,
        public readonly string $paraCumplimiento,
        public readonly string $fechaUltimoMantenimiento,
        public readonly string $hoy,
        public readonly string $dias,
        public readonly string $vencimiento,
        public readonly string $proximaFecha,
        public readonly string $estadoProgramacion
    ) {}

    public function toArray(): array
    {
        return [
            $this->equipoComputo,
            $this->marca,
            $this->modelo,
            $this->area,
            $this->personalEncargado,
            $this->serial,
            $this->propiedadEmpleado,
            $this->ipFijaLocal,
            $this->numeroInventario,
            $this->sede,
            $this->procesador,
            $this->memoriaRam,
            $this->windows,
            $this->office,
            $this->nitro,
            $this->paraCumplimiento,
            $this->fechaUltimoMantenimiento,
            $this->hoy,
            $this->dias,
            $this->vencimiento,
            $this->proximaFecha,
            $this->estadoProgramacion
        ];
    }
}
