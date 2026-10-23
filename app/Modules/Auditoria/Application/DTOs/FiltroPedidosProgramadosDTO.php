<?php

namespace App\Modules\Auditoria\Application\DTOs;

use Illuminate\Http\Request;

class FiltroPedidosProgramadosDTO
{
    public function __construct(
        public readonly ?string $fechaInicio = null,
        public readonly ?string $fechaFin = null,
        public readonly ?string $estado = null,
        public readonly ?int $creadoPor = null,
        public readonly ?string $buscar = null,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $fechaInicio = $request->input('fecha_inicio', $request->input('fechaInicio'));
        $fechaFin = $request->input('fecha_fin', $request->input('fechaFin'));
        $estado = $request->input('estado');
        $creadoPor = $request->input('creado_por', $request->input('creadoPor'));
        $buscar = $request->input('buscar', $request->input('q'));

        $page = (int) $request->input('page', $request->input('pagina', 1));
        $perPage = (int) $request->input('per_page', $request->input('limite', 15));

        return new self(
            fechaInicio: !empty($fechaInicio) ? trim((string) $fechaInicio) : null,
            fechaFin: !empty($fechaFin) ? trim((string) $fechaFin) : null,
            estado: !empty($estado) ? trim((string) $estado) : null,
            creadoPor: !empty($creadoPor) ? (int) $creadoPor : null,
            buscar: !empty($buscar) ? trim((string) $buscar) : null,
            page: max(1, $page),
            perPage: max(1, min(100, $perPage))
        );
    }
}
