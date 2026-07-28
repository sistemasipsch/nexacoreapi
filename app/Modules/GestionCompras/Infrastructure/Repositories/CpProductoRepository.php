<?php

namespace App\Modules\GestionCompras\Infrastructure\Repositories;

use App\Models\CpProducto;

class CpProductoRepository
{
    public function getAll(?string $search = null, int $perPage = 20)
    {
        $query = CpProducto::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'LIKE', "%{$search}%")
                  ->orWhere('codigo', 'LIKE', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function getAllWithoutLimit()
    {
        return CpProducto::all();
    }

    public function create(array $data)
    {
        return CpProducto::create($data);
    }

    public function updateOrCreateByCodigo($codigo, array $data)
    {
        return CpProducto::updateOrCreate(
            ['codigo' => $codigo],
            $data
        );
    }

    public function find($id)
    {
        return CpProducto::find($id);
    }

    public function update($id, array $data)
    {
        $item = CpProducto::find($id);
        if ($item) {
            $item->update($data);
        }
        return $item;
    }

    public function delete($id)
    {
        $item = CpProducto::find($id);
        if ($item) {
            $item->delete();
            return true;
        }
        return false;
    }
}
