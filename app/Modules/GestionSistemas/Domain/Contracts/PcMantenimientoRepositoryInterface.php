<?php

namespace App\Modules\GestionSistemas\Domain\Contracts;

interface PcMantenimientoRepositoryInterface
{
    public function getAll(?int $sedeId = null, ?string $tipoMantenimiento = null);
    public function find(int $id);
    public function getByEquipo(int $equipoId);
    public function create(array $data);
    public function update(int $id, array $data);
    public function delete(int $id): bool;
}
