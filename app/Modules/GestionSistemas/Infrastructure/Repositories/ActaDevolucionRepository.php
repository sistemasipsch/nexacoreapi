<?php

namespace App\Modules\GestionSistemas\Infrastructure\Repositories;

use App\Models\PcDevuelto;
use App\Models\PcEntrega;
use App\Modules\GestionSistemas\Domain\Entities\ActaDevolucion;
use Illuminate\Support\Facades\DB;

class ActaDevolucionRepository
{
    public function save(ActaDevolucion $acta): ActaDevolucion
    {
        DB::beginTransaction();
        try {
            $modelo = new PcDevuelto();
            $modelo->entrega_id = $acta->getEntregaId();
            $modelo->fecha_devolucion = $acta->getFechaDevolucion();
            $modelo->observaciones = $acta->getObservaciones() ?? '';
            $modelo->firma_entrega = $acta->getFirmaEntrega() ?? '';
            $modelo->firma_recibe = $acta->getFirmaRecibe() ?? '';
            $modelo->save();

            // Actualizar la entrega a devuelta
            $entrega = PcEntrega::find($acta->getEntregaId());
            if ($entrega) {
                $entrega->devuelto = $acta->getFechaDevolucion();
                $entrega->save();
            }

            DB::commit();

            return new ActaDevolucion(
                $modelo->entrega_id,
                $modelo->fecha_devolucion->format('Y-m-d'),
                $modelo->observaciones,
                $modelo->firma_entrega,
                $modelo->firma_recibe,
                $modelo->id
            );
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function findById(int $id): ?ActaDevolucion
    {
        $modelo = PcDevuelto::find($id);
        if (!$modelo) {
            return null;
        }

        return new ActaDevolucion(
            $modelo->entrega_id,
            $modelo->fecha_devolucion->format('Y-m-d'),
            $modelo->observaciones,
            $modelo->firma_entrega,
            $modelo->firma_recibe,
            $modelo->id
        );
    }

    public function update(ActaDevolucion $acta): ?ActaDevolucion
    {
        DB::beginTransaction();
        try {
            $modelo = PcDevuelto::find($acta->getId());
            if (!$modelo) {
                return null;
            }

            $modelo->entrega_id = $acta->getEntregaId();
            $modelo->fecha_devolucion = $acta->getFechaDevolucion();
            $modelo->observaciones = $acta->getObservaciones();
            
            if ($acta->getFirmaEntrega() !== null) {
                $modelo->firma_entrega = $acta->getFirmaEntrega();
            }
            if ($acta->getFirmaRecibe() !== null) {
                $modelo->firma_recibe = $acta->getFirmaRecibe();
            }
            
            $modelo->save();

            // Actualizar la entrega a devuelta
            $entrega = PcEntrega::find($acta->getEntregaId());
            if ($entrega) {
                $entrega->devuelto = $acta->getFechaDevolucion();
                $entrega->save();
            }

            DB::commit();

            return new ActaDevolucion(
                $modelo->entrega_id,
                $modelo->fecha_devolucion ? $modelo->fecha_devolucion->format('Y-m-d') : date('Y-m-d'),
                $modelo->observaciones,
                $modelo->firma_entrega,
                $modelo->firma_recibe,
                $modelo->id
            );
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        $modelo = PcDevuelto::find($id);
        if (!$modelo) {
            return false;
        }

        // Revertir entrega
        $entrega = PcEntrega::find($modelo->entrega_id);
        if ($entrega) {
            $entrega->devuelto = null;
            $entrega->save();
        }

        return $modelo->delete();
    }
}

