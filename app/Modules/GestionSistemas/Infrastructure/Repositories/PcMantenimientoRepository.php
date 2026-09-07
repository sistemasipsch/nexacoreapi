<?php

namespace App\Modules\GestionSistemas\Infrastructure\Repositories;

use App\Models\PcMantenimiento;
use App\Modules\GestionSistemas\Domain\Contracts\PcMantenimientoRepositoryInterface;

class PcMantenimientoRepository implements PcMantenimientoRepositoryInterface
{
    public function getAll(?int $sedeId = null, ?string $tipoMantenimiento = null)
    {
        $query = PcMantenimiento::with(['equipo.sede', 'equipo.area', 'empresaResponsable', 'creador:id,nombre_completo'])
            ->orderBy('id', 'desc');

        if ($sedeId) {
            $query->whereHas('equipo', function($q) use ($sedeId) {
                $q->where('sede_id', $sedeId);
            });
        }

        if ($tipoMantenimiento && !in_array($tipoMantenimiento, ['all', 'todos', 'todas', ''], true)) {
            $query->where('tipo_mantenimiento', $tipoMantenimiento);
        }

        return $query->get();
    }

    public function find(int $id)
    {
        return PcMantenimiento::with([
            'equipo.sede',
            'equipo.area',
            'equipo.responsable',
            'equipo.caracteristicasTecnicas',
            'empresaResponsable',
            'creador:id,nombre_completo'
        ])->find($id);
    }

    public function getByEquipo(int $equipoId)
    {
        return PcMantenimiento::with(['empresaResponsable', 'creador:id,nombre_completo'])
            ->where('equipo_id', $equipoId)
            ->get();
    }

    public function create(array $data)
    {
        if (!isset($data['fecha_creacion'])) {
            $data['fecha_creacion'] = now();
        }
        if (!isset($data['estado'])) {
            $data['estado'] = 'pendiente';
        }

        return PcMantenimiento::create($data);
    }

    public function update(int $id, array $data)
    {
        $item = PcMantenimiento::find($id);
        if ($item) {
            $item->update($data);
            return $item;
        }
        return null;
    }

    public function delete(int $id): bool
    {
        $item = PcMantenimiento::find($id);
        if ($item) {
            return $item->delete();
        }
        return false;
    }
}
