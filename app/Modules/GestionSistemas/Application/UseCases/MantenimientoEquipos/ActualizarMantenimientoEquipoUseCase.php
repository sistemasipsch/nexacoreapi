<?php

namespace App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos;

use App\Modules\GestionSistemas\Domain\Contracts\PcMantenimientoRepositoryInterface;
use App\Services\PcMantenimientoFirmaService;

class ActualizarMantenimientoEquipoUseCase
{
    private PcMantenimientoRepositoryInterface $repository;
    private PcMantenimientoFirmaService $firmaService;

    public function __construct(PcMantenimientoRepositoryInterface $repository, PcMantenimientoFirmaService $firmaService)
    {
        $this->repository = $repository;
        $this->firmaService = $firmaService;
    }

    public function execute(int $id, array $data)
    {
        // Cast de booleanos que pueden venir como string 'true'/'false' en FormData
        $booleanFields = ['repuesto', 'cpu', 'pantalla', 'teclado', 'mouse', 'unidad_cd'];
        foreach ($booleanFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }

        // Procesar Fotos si vienen como UploadedFile
        if (isset($data['foto_antes']) && $data['foto_antes'] instanceof \Illuminate\Http\UploadedFile) {
            $data['foto_antes'] = $data['foto_antes']->store('pcMantenimientos', 'public');
        }
        if (isset($data['foto_despues']) && $data['foto_despues'] instanceof \Illuminate\Http\UploadedFile) {
            $data['foto_despues'] = $data['foto_despues']->store('pcMantenimientos', 'public');
        }

        // Procesar Firmas si vienen en la data de actualización
        if (isset($data['firma_personal_cargo'])) {
            $data['firma_personal_cargo'] = $this->firmaService->saveBase64Signature($data['firma_personal_cargo']);
        }
        
        if (isset($data['firma_sistemas'])) {
            $data['firma_sistemas'] = $this->firmaService->saveBase64Signature($data['firma_sistemas']);
        }

        return $this->repository->update($id, $data);
    }
}
