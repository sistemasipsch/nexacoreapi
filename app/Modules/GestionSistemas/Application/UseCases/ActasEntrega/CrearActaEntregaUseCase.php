<?php

namespace App\Modules\GestionSistemas\Application\UseCases\ActasEntrega;

use App\Modules\GestionSistemas\Application\DTOs\CrearActaEntregaDTO;
use App\Modules\GestionSistemas\Domain\Contracts\ActaEntregaRepositoryInterface;
use App\Modules\GestionSistemas\Domain\Entities\ActaEntrega;
use App\Modules\GestionSistemas\Domain\Entities\PerifericoEntregado;
use Illuminate\Support\Facades\Storage;
use App\Services\SignatureHelper;
use App\Models\Personal;
use Exception;

class CrearActaEntregaUseCase
{
    private ActaEntregaRepositoryInterface $repository;

    public function __construct(ActaEntregaRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(CrearActaEntregaDTO $dto): ActaEntrega
    {
        $firmaEntregaPath = null;
        $firmaRecibePath = null;

        // Firma Quien Entrega
        if ($dto->firmaGuardadaEntregaPath) {
            $firmaEntregaPath = SignatureHelper::cleanRelativePath($dto->firmaGuardadaEntregaPath);
        } elseif ($dto->firmaEntrega) {
            $firmaEntregaPath = SignatureHelper::processSignature($dto->firmaEntrega, 'ActasEntregaEquipos', 'firma_entrega');
            if (!$firmaEntregaPath) {
                throw new Exception('Error al procesar la firma de entrega.');
            }
        }

        // Firma Quien Recibe
        if ($dto->firmaGuardadaRecibePath) {
            $firmaRecibePath = SignatureHelper::cleanRelativePath($dto->firmaGuardadaRecibePath);
        } elseif ($dto->firmaRecibe) {
            $firmaRecibePath = SignatureHelper::processSignature($dto->firmaRecibe, 'ActasEntregaEquipos', 'firma_recibe');
            if (!$firmaRecibePath) {
                throw new Exception('Error al procesar la firma de quien recibe.');
            }
        } else {
            // Si no se envió firma explícita, verificar si el funcionario ya tiene firma guardada
            $funcionario = Personal::find($dto->funcionarioId);
            if ($funcionario && !empty($funcionario->firma)) {
                $firmaRecibePath = SignatureHelper::cleanRelativePath($funcionario->firma);
            }
        }

        $perifericos = [];
        foreach ($dto->perifericos as $perifericoDTO) {
            $perifericos[] = new PerifericoEntregado(
                $perifericoDTO->inventarioId,
                $perifericoDTO->cantidad,
                $perifericoDTO->observaciones
            );
        }

        $actaEntrega = new ActaEntrega(
            $dto->equipoId,
            $dto->funcionarioId,
            $dto->fechaEntrega,
            $firmaEntregaPath,
            $firmaRecibePath,
            'entregado', // estado
            null, // devuelto
            $perifericos
        );

        return $this->repository->save($actaEntrega);
    }
}
