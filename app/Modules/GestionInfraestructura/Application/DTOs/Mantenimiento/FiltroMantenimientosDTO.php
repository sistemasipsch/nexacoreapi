<?php

namespace App\Modules\GestionInfraestructura\Application\DTOs\Mantenimiento;

use Illuminate\Http\Request;

class FiltroMantenimientosDTO
{
    public readonly int $page;
    public readonly int $limit;
    public readonly ?string $tecnico;
    public readonly ?string $fecha_inicio;
    public readonly ?string $fecha_fin;
    public readonly ?int $sede_id;

    public function __construct(Request $request)
    {
        $this->page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 20);
        
        // Limitar máximo a 300, default 20 si es menor a 20
        $this->limit = $limit > 300 ? 300 : ($limit < 20 ? 20 : $limit);
        
        $this->tecnico = $request->query('tecnico');
        $this->fecha_inicio = $request->query('fecha_inicio');
        $this->fecha_fin = $request->query('fecha_fin');
        
        $sede = $request->query('sede_id');
        $this->sede_id = $sede ? (int) $sede : null;
    }
}
