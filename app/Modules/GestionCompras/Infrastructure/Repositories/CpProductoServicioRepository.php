<?php

namespace App\Modules\GestionCompras\Infrastructure\Repositories;

use App\Models\CpProductoServicio;

class CpProductoServicioRepository
{
    public function getAll($search = null, $perPage = 15)
    {
        $query = CpProductoServicio::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('codigo_producto', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function getAllWithoutLimit()
    {
        return CpProductoServicio::all();
    }

    public function create(array $data)
    {
        return CpProductoServicio::create($data);
    }

    public function updateOrCreateByCodigoProducto(string $codigoProducto, array $data)
    {
        return CpProductoServicio::updateOrCreate(
            ['codigo_producto' => $codigoProducto],
            $data
        );
    }


    public function find($id)
    {
        return CpProductoServicio::find($id);
    }

    public function update($id, array $data)
    {
        $item = CpProductoServicio::find($id);
        if ($item) {
            $item->update($data);
        }
        return $item;
    }

    public function delete($id)
    {
        $item = CpProductoServicio::find($id);
        if ($item) {
            $item->delete();
            return true;
        }
        return false;
    }
}
