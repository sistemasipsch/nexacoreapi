<?php

namespace App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos;

use App\Modules\GestionSistemas\Domain\Contracts\PcMantenimientoRepositoryInterface;
use Exception;

class ObtenerMantenimientoEquipoUseCase
{
    private PcMantenimientoRepositoryInterface $repository;

    public function __construct(PcMantenimientoRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(int $id)
    {
        $mantenimiento = $this->repository->find($id);

        if (!$mantenimiento) {
            throw new Exception('Mantenimiento no encontrado', 404);
        }

        if ($mantenimiento->foto_antes) {
            $mantenimiento->foto_antes_url = asset('storage/' . $mantenimiento->foto_antes);
        }

        if ($mantenimiento->foto_despues) {
            $mantenimiento->foto_despues_url = asset('storage/' . $mantenimiento->foto_despues);
        }

        return $mantenimiento;
    }
}
