<?php

namespace App\Modules\GestionSistemas\Application\UseCases\EquiposComputo;

use App\Modules\GestionSistemas\Domain\Contracts\PcEquipoRepositoryInterface;
use App\Models\PcEquipo;

class ActualizarPcEquipoUseCase
{
    private PcEquipoRepositoryInterface $repository;

    public function __construct(PcEquipoRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(int $id, array $data): ?PcEquipo
    {
        $equipo = PcEquipo::find($id);
        if (!$equipo) {
            return null;
        }

        return $this->repository->update($id, $data);
    }
}
