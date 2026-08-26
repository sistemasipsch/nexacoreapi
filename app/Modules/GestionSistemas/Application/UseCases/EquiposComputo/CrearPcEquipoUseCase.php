<?php

namespace App\Modules\GestionSistemas\Application\UseCases\EquiposComputo;

use App\Modules\GestionSistemas\Domain\Contracts\PcEquipoRepositoryInterface;
use App\Models\PcEquipo;
use Illuminate\Http\UploadedFile;

class CrearPcEquipoUseCase
{
    private PcEquipoRepositoryInterface $repository;

    public function __construct(PcEquipoRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(array $data): PcEquipo
    {
        if (isset($data['imagen']) && $data['imagen'] instanceof UploadedFile) {
            $path = $data['imagen']->store('pcEquipos', 'public');
            $data['imagen_url'] = 'storage/' . $path;
            unset($data['imagen']);
        } elseif (isset($data['imagen_file']) && $data['imagen_file'] instanceof UploadedFile) {
            $path = $data['imagen_file']->store('pcEquipos', 'public');
            $data['imagen_url'] = 'storage/' . $path;
            unset($data['imagen_file']);
        }

        return $this->repository->create($data);
    }
}
