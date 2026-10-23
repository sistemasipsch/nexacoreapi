<?php

namespace App\Modules\Auditoria\Domain\Contracts;

use App\Modules\Auditoria\Application\DTOs\FiltroActividadesDTO;
use App\Modules\Auditoria\Domain\Entities\ActividadEntity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ActividadRepositoryInterface
{
    /**
     * Lista actividades de forma paginada aplicando filtros.
     */
    public function listarPaginado(FiltroActividadesDTO $filtro): LengthAwarePaginator;

    /**
     * Obtiene una actividad específica por su ID.
     */
    public function obtenerPorId(int $id): ?ActividadEntity;

    /**
     * Obtiene métricas o estadísticas rápidas de las actividades.
     */
    public function obtenerEstadisticas(FiltroActividadesDTO $filtro): array;

    /**
     * Obtiene el listado de tablas afectadas únicas registradas.
     */
    public function obtenerTablasAfectadas(): array;
}
