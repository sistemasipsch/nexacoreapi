<?php

namespace App\Modules\GestionInfraestructura\Application\DTOs\Equipos;

use Illuminate\Http\Request;

class FiltroInfraEquiposDTO
{
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?int $sede_id = null,
        public readonly ?int $area_id = null,
        public readonly ?string $tipo_equipo = null,
        public readonly ?string $estado_equipo = null,
        public readonly int $per_page = 15,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            q: $request->input('q'),
            sede_id: $request->filled('sede_id') ? (int) $request->input('sede_id') : null,
            area_id: $request->filled('area_id') ? (int) $request->input('area_id') : null,
            tipo_equipo: $request->input('tipo_equipo'),
            estado_equipo: $request->input('estado_equipo'),
            per_page: $request->filled('per_page') ? max(1, (int) $request->input('per_page')) : 15,
        );
    }
}
