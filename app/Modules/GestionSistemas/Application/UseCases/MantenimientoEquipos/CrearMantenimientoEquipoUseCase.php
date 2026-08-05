<?php

namespace App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos;

use App\Modules\GestionSistemas\Domain\Contracts\PcMantenimientoRepositoryInterface;
use App\Services\PcMantenimientoFirmaService;
use Exception;

class CrearMantenimientoEquipoUseCase
{
    private PcMantenimientoRepositoryInterface $repository;
    private PcMantenimientoFirmaService $firmaService;

    public function __construct(PcMantenimientoRepositoryInterface $repository, PcMantenimientoFirmaService $firmaService)
    {
        $this->repository = $repository;
        $this->firmaService = $firmaService;
    }

    public function execute(array $data)
    {
        // Cast de booleanos que pueden venir como string 'true'/'false' en FormData
        $data['repuesto'] = filter_var($data['repuesto'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $data['use_stored_signature_sistemas'] = filter_var($data['use_stored_signature_sistemas'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Campos de limpieza por defecto a false si no se envían
        $data['cpu'] = filter_var($data['cpu'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $data['pantalla'] = filter_var($data['pantalla'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $data['teclado'] = filter_var($data['teclado'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $data['mouse'] = filter_var($data['mouse'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $data['unidad_cd'] = filter_var($data['unidad_cd'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Procesar Fotos si vienen como UploadedFile
        if (isset($data['foto_antes']) && $data['foto_antes'] instanceof \Illuminate\Http\UploadedFile) {
            $data['foto_antes'] = $data['foto_antes']->store('pcMantenimientos', 'public');
        }
        if (isset($data['foto_despues']) && $data['foto_despues'] instanceof \Illuminate\Http\UploadedFile) {
            $data['foto_despues'] = $data['foto_despues']->store('pcMantenimientos', 'public');
        }

        // Procesar Firmas
        if (!empty($data['firma_personal_cargo'])) {
            $data['firma_personal_cargo'] = $this->firmaService->saveBase64Signature($data['firma_personal_cargo']);
        }
        
        if (!empty($data['use_stored_signature_sistemas']) && $data['use_stored_signature_sistemas']) {
            if (auth()->check() && auth()->user()->firma_digital) {
                $data['firma_sistemas'] = auth()->user()->firma_digital;
            } else {
                throw new Exception('El usuario no tiene una firma digital configurada');
            }
        } elseif (!empty($data['firma_sistemas'])) {
            $data['firma_sistemas'] = $this->firmaService->saveBase64Signature($data['firma_sistemas']);
        }

        return $this->repository->create($data);
    }
}
