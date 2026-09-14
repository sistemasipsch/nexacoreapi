<?php

namespace App\Modules\GestionCompras\Application\UseCases\Inventario;

use App\Models\Inventario;

class ListarInventarioUseCase
{
    public function execute($search, $sede_id, $responsable_id, $coordinador_id, $perPage = 100)
    {
        $query = Inventario::query();

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%")
                    ->orWhere('marca', 'like', "%{$search}%")
                    ->orWhere('modelo', 'like', "%{$search}%")
                    ->orWhere('serial', 'like', "%{$search}%")
                    ->orWhere('codigo_barras', 'like', "%{$search}%");
            });
        }

        if (!empty($sede_id) && $sede_id !== 'todas' && $sede_id !== 'all') {
            $query->where('sede_id', $sede_id);
        }

        if (!empty($responsable_id) && $responsable_id !== 'todos' && $responsable_id !== 'all') {
            $query->where('responsable_id', $responsable_id);
        }

        if (!empty($coordinador_id) && $coordinador_id !== 'todos' && $coordinador_id !== 'all') {
            $query->where('coordinador_id', $coordinador_id);
        }

        return $query->with(['responsablePersonal', 'coordinadorPersonal', 'sede', 'proceso'])
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }
}