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

        // 1. Resolver técnico responsable directamente desde la tabla personal
        $tecnicoPersonal = match ((int) ($mantenimiento->creado_por)) {
            7 => \App\Models\Personal::find(1276),
            73 => \App\Models\Personal::find(1665),
            65 => \App\Models\Personal::find(1666),
            default => null
        };

        if (!$tecnicoPersonal) {
            $nombreRaw = $mantenimiento->responsable_mantenimiento ?: ($mantenimiento->creador?->nombre_completo ?? '');
            if (stripos($nombreRaw, 'Ashly') !== false || stripos($nombreRaw, '73') !== false) {
                $tecnicoPersonal = \App\Models\Personal::find(1665);
            } elseif (stripos($nombreRaw, 'Kevin') !== false || stripos($nombreRaw, '65') !== false) {
                $tecnicoPersonal = \App\Models\Personal::find(1666);
            } elseif (stripos($nombreRaw, 'Prosperini') !== false || stripos($nombreRaw, 'Sergio') !== false || stripos($nombreRaw, '7') !== false) {
                $tecnicoPersonal = \App\Models\Personal::find(1276);
            } else {
                $tecnicoPersonal = \App\Models\Personal::where('nombre', 'LIKE', '%' . $nombreRaw . '%')->first();
            }
        }

        $tecnicoNombre = $tecnicoPersonal?->nombre ?: ($mantenimiento->responsable_mantenimiento ?: ($mantenimiento->creador?->nombre_completo ?? 'Técnico de Sistemas'));
        $tecnicoCedula = $tecnicoPersonal?->cedula ?? null;

        $mantenimiento->responsable_mantenimiento = $tecnicoNombre;
        $mantenimiento->tecnico_nombre = $tecnicoNombre;
        $mantenimiento->tecnico_cedula = $tecnicoCedula;

        // 2. Resolver y corregir firmas (incluyendo firmas cruzadas)
        $rawPersonal = $mantenimiento->getRawOriginal('firma_personal_cargo');
        $rawSistemas = $mantenimiento->getRawOriginal('firma_sistemas');

        // Si firma_sistemas está vacía pero firma_personal_cargo tiene firma (o si contiene firmas de sistemas)
        if (empty($rawSistemas) && !empty($rawPersonal)) {
            $rawSistemas = $rawPersonal;
            $rawPersonal = null;
        }

        // Si el funcionario no tiene firma directa en el acta, traer de la tabla personal
        if (empty($rawPersonal) && $mantenimiento->equipo && $mantenimiento->equipo->responsable) {
            $rawPersonal = $mantenimiento->equipo->responsable->firma;
        }

        // Si el técnico no tiene firma en el acta, fallback al perfil o personal
        if (empty($rawSistemas)) {
            if ($mantenimiento->creador && $mantenimiento->creador->firma_digital) {
                $rawSistemas = $mantenimiento->creador->getRawOriginal('firma_digital');
            } elseif ($tecnicoNombre) {
                $tecnicoPersonal = \App\Models\Personal::where('nombre', 'LIKE', '%' . $tecnicoNombre . '%')
                    ->whereNotNull('firma')
                    ->first();
                if ($tecnicoPersonal && $tecnicoPersonal->firma) {
                    $rawSistemas = $tecnicoPersonal->firma;
                }
            }
        }

        // Formatear URLs de salida
        if ($rawPersonal) {
            $mantenimiento->firma_personal_cargo = str_starts_with($rawPersonal, 'http')
                ? $rawPersonal
                : asset('storage/' . ltrim(str_replace('storage/', '', $rawPersonal), '/'));
        } else {
            $mantenimiento->firma_personal_cargo = null;
        }

        if ($rawSistemas) {
            $mantenimiento->firma_sistemas = str_starts_with($rawSistemas, 'http')
                ? $rawSistemas
                : asset('storage/' . ltrim(str_replace('storage/', '', $rawSistemas), '/'));
        } else {
            $mantenimiento->firma_sistemas = null;
        }

        return $mantenimiento;
    }
}
