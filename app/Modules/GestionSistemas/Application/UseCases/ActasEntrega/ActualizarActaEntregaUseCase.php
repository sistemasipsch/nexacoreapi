<?php

namespace App\Modules\GestionSistemas\Application\UseCases\ActasEntrega;

use App\Modules\GestionSistemas\Application\DTOs\ActualizarActaEntregaDTO;
use App\Modules\GestionSistemas\Domain\Contracts\ActaEntregaRepositoryInterface;
use App\Modules\GestionSistemas\Domain\Entities\ActaEntrega;
use App\Modules\GestionSistemas\Domain\Entities\PerifericoEntregado;
use Illuminate\Support\Facades\Storage;
use Exception;

class ActualizarActaEntregaUseCase
{
    private ActaEntregaRepositoryInterface $repository;

    public function __construct(ActaEntregaRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(ActualizarActaEntregaDTO $dto): ActaEntrega
    {
        $acta = $this->repository->findById($dto->getId());

        if (!$acta) {
            throw new Exception("Acta de entrega no encontrada");
        }

        // Update fields if provided
        if ($dto->getEquipoId() !== null) {
            $acta->setEquipoId($dto->getEquipoId());
        }
        
        if ($dto->getFuncionarioId() !== null) {
            $acta->setFuncionarioId($dto->getFuncionarioId());
        }

        if ($dto->getFechaEntrega() !== null) {
            $acta->setFechaEntrega($dto->getFechaEntrega());
        }

        if ($dto->getEstado() !== null) {
            $acta->setEstado($dto->getEstado());
        }

        if ($dto->getDevuelto() !== null) {
            $acta->setDevuelto($dto->getDevuelto());
        }

        // Procesar archivos si se enviaron
        if ($dto->getFirmaGuardadaEntregaPath()) {
            if ($acta->getFirmaEntrega() && !str_starts_with($acta->getFirmaEntrega(), 'signatures/')) {
                Storage::disk('public')->delete(str_replace('storage/', '', $acta->getFirmaEntrega()));
            }
            $acta->setFirmaEntrega($dto->getFirmaGuardadaEntregaPath());
        } elseif ($dto->getFirmaEntrega()) {
            if ($acta->getFirmaEntrega() && !str_starts_with($acta->getFirmaEntrega(), 'signatures/')) {
                Storage::disk('public')->delete(str_replace('storage/', '', $acta->getFirmaEntrega()));
            }
            $pathEntrega = $dto->getFirmaEntrega()->store('ActasEntregaEquipos', 'public');
            $acta->setFirmaEntrega($pathEntrega);
        }

        if ($dto->getFirmaGuardadaRecibePath()) {
            if ($acta->getFirmaRecibe() && !str_starts_with($acta->getFirmaRecibe(), 'personal_firmas/')) {
                Storage::disk('public')->delete(str_replace('storage/', '', $acta->getFirmaRecibe()));
            }
            $acta->setFirmaRecibe($dto->getFirmaGuardadaRecibePath());
        } elseif ($dto->getFirmaRecibe()) {
            if ($acta->getFirmaRecibe() && !str_starts_with($acta->getFirmaRecibe(), 'personal_firmas/')) {
                Storage::disk('public')->delete(str_replace('storage/', '', $acta->getFirmaRecibe()));
            }
            $pathRecibe = $dto->getFirmaRecibe()->store('ActasEntregaEquipos', 'public');
            $acta->setFirmaRecibe($pathRecibe);
        }

        // Actualizar periféricos
        if ($dto->getPerifericos() !== null) {
            // Eliminar periféricos anteriores en DB (el repositorio ya borra si save() lo requiere? 
            // WAIT, el save() del repo guarda los que se le pasan. 
            // Si $dto->getPerifericos() viene con nuevos, el repo de Eloquent no borra los viejos si no le decimos.
            // Es necesario indicarle al repo que reemplace, pero lo podemos hacer vaciando en el Repo o limpiando aquí.
            // Para simplificar, el repositorio actual no elimina periféricos viejos en save(), solo hace update o insert.
            // Si pasamos la lista de periféricos, el ActaEntrega debe contenerlos.
            $perifericosEntities = [];
            foreach ($dto->getPerifericos() as $pDto) {
                $perifericosEntities[] = new PerifericoEntregado(
                    $pDto->inventarioId,
                    $pDto->cantidad,
                    $pDto->observaciones
                );
            }
            
            // Para poder reemplazar correctamente, eliminaremos los viejos en la DB directamente
            // y guardaremos los nuevos.
            \App\Models\PcPerifericoEntregado::where('entrega_id', $acta->getId())->delete();
            $acta->setPerifericos($perifericosEntities);
        }

        return $this->repository->save($acta);
    }
}
