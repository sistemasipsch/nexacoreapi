<?php

namespace App\Modules\GestionSistemas\Infrastructure\Repositories;

use App\Models\PcEntrega;
use App\Models\PcPerifericoEntregado;
use App\Modules\GestionSistemas\Domain\Contracts\ActaEntregaRepositoryInterface;
use App\Modules\GestionSistemas\Domain\Entities\ActaEntrega;
use App\Modules\GestionSistemas\Domain\Entities\PerifericoEntregado;
use Illuminate\Support\Facades\DB;
use Exception;

class ActaEntregaRepository implements ActaEntregaRepositoryInterface
{
    public function save(ActaEntrega $actaEntrega): ActaEntrega
    {
        DB::beginTransaction();
        try {
            $entregaModel = new PcEntrega();
            if ($actaEntrega->getId()) {
                $entregaModel = PcEntrega::findOrFail($actaEntrega->getId());
            }

            $entregaModel->equipo_id = $actaEntrega->getEquipoId();
            $entregaModel->funcionario_id = $actaEntrega->getFuncionarioId();
            $entregaModel->fecha_entrega = $actaEntrega->getFechaEntrega();
            $entregaModel->estado = $actaEntrega->getEstado();
            
            if ($actaEntrega->getFirmaEntrega() !== null) {
                $entregaModel->firma_entrega = $actaEntrega->getFirmaEntrega();
            }
            if ($actaEntrega->getFirmaRecibe() !== null) {
                $entregaModel->firma_recibe = $actaEntrega->getFirmaRecibe();
            }
            if ($actaEntrega->getDevuelto() !== null) {
                $entregaModel->devuelto = $actaEntrega->getDevuelto();
            }

            $entregaModel->save();

            // Guardar perifericos
            foreach ($actaEntrega->getPerifericos() as $periferico) {
                $perifericoModel = new PcPerifericoEntregado();
                if ($periferico->getId()) {
                    $perifericoModel = PcPerifericoEntregado::find($periferico->getId()) ?: new PcPerifericoEntregado();
                }

                $perifericoModel->entrega_id = $entregaModel->id;
                $perifericoModel->inventario_id = $periferico->getInventarioId();
                $perifericoModel->cantidad = $periferico->getCantidad();
                $perifericoModel->observaciones = $periferico->getObservaciones();
                $perifericoModel->save();
            }

            DB::commit();

            return $this->toEntity($entregaModel);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function findById(int $id): ?ActaEntrega
    {
        $model = PcEntrega::with('perifericos')->find($id);
        if (!$model) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findAll(): array
    {
        $models = PcEntrega::with('perifericos')->get();
        return $models->map(fn ($m) => $this->toEntity($m))->toArray();
    }

    private function toEntity(PcEntrega $model): ActaEntrega
    {
        $perifericosEntities = [];
        if ($model->relationLoaded('perifericos') || $model->perifericos) {
            foreach ($model->perifericos as $pModel) {
                $perifericosEntities[] = new PerifericoEntregado(
                    $pModel->inventario_id,
                    $pModel->cantidad,
                    $pModel->observaciones,
                    $pModel->id
                );
            }
        }

        $fechaEntrega = date('Y-m-d');
        if ($model->fecha_entrega) {
            $fechaEntrega = $model->fecha_entrega instanceof \Carbon\Carbon 
                ? $model->fecha_entrega->format('Y-m-d') 
                : substr((string)$model->fecha_entrega, 0, 10);
        }

        $fechaDevuelto = null;
        if ($model->devuelto) {
            $fechaDevuelto = $model->devuelto instanceof \Carbon\Carbon 
                ? $model->devuelto->format('Y-m-d') 
                : substr((string)$model->devuelto, 0, 10);
        }

        return new ActaEntrega(
            $model->equipo_id,
            $model->funcionario_id,
            $fechaEntrega,
            $model->firma_entrega,
            $model->firma_recibe,
            $model->estado,
            $fechaDevuelto,
            $perifericosEntities,
            $model->id
        );
    }

    public function delete(int $id): bool
    {
        $model = PcEntrega::find($id);
        if (!$model) {
            return false;
        }
        
        // Relaciones: los periféricos en BD podrían tener onDelete('cascade')
        // Si no lo tienen, los borramos manualmente.
        PcPerifericoEntregado::where('entrega_id', $id)->delete();
        
        return $model->delete();
    }
}
