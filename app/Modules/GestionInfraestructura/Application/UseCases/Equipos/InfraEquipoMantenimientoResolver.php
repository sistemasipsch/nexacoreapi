<?php

namespace App\Modules\GestionInfraestructura\Application\UseCases\Equipos;

use App\Models\InfraEquipo;
use App\Models\Mantenimiento;
use Illuminate\Support\Collection;

/**
 * Relaciona mantenimientos con equipos de infraestructura.
 *
 * Los mantenimientos nuevos guardan equipo_infraestructura_id, pero los históricos solo
 * registran el activo fijo en "codigo" (MAQ0005, "MAQ 0034", "0007"...) y a veces el serial.
 * Se considera que un mantenimiento pertenece al equipo si:
 *  - tiene su equipo_infraestructura_id, o
 *  - el serial coincide, o
 *  - el número del activo fijo coincide y es de la misma sede (actual o de origen del traslado).
 */
class InfraEquipoMantenimientoResolver
{
    public const RELACIONES_MANTENIMIENTO = ['creador', 'coordinador', 'revisador'];

    /** Todos los mantenimientos del equipo, del más antiguo al más reciente */
    public function mantenimientosDeEquipo(InfraEquipo $equipo): Collection
    {
        $serial = $this->normalizar($equipo->serial);
        $clave = $this->claveActivo($equipo->activo_fijo);
        $sedes = array_values(array_filter([$equipo->sede_id, $equipo->traslado_sede_id]));

        return Mantenimiento::with(self::RELACIONES_MANTENIMIENTO)
            ->where(function ($q) use ($equipo, $serial, $clave, $sedes) {
                $q->where('equipo_infraestructura_id', $equipo->id);
                if ($serial !== '') {
                    $q->orWhereNotNull('serial');
                }
                if ($clave !== '' && $sedes) {
                    $q->orWhere(fn ($w) => $w->whereIn('sede_id', $sedes)->whereNotNull('codigo'));
                }
            })
            ->get()
            ->filter(fn (Mantenimiento $m) => $this->perteneceA($m, $equipo))
            ->sortBy(fn ($m) => [optional($m->fecha_creacion)->timestamp ?? 0, $m->id])
            ->values();
    }

    /** Equipo al que corresponde un mantenimiento (o null si no se puede identificar) */
    public function equipoDeMantenimiento(Mantenimiento $mant): ?InfraEquipo
    {
        if ($mant->equipo_infraestructura_id) {
            $equipo = InfraEquipo::find($mant->equipo_infraestructura_id);
            if ($equipo) {
                return $equipo;
            }
        }

        $candidatos = InfraEquipo::all()->filter(fn (InfraEquipo $e) => $this->perteneceA($mant, $e));

        // Si hay varios, priorizar coincidencia por serial y luego por sede actual
        $serialMant = $this->normalizar($mant->serial);
        return $candidatos
            ->sortByDesc(fn (InfraEquipo $e) => [
                $serialMant !== '' && $this->normalizar($e->serial) === $serialMant ? 1 : 0,
                (int) $e->sede_id === (int) $mant->sede_id ? 1 : 0,
            ])
            ->first();
    }

    public function perteneceA(Mantenimiento $m, InfraEquipo $e): bool
    {
        if ((int) $m->equipo_infraestructura_id === (int) $e->id && $m->equipo_infraestructura_id) {
            return true;
        }
        // Si el mantenimiento ya está vinculado a otro equipo, no se reasigna
        if ($m->equipo_infraestructura_id) {
            return false;
        }

        $serialEq = $this->normalizar($e->serial);
        if (strlen($serialEq) >= 4 && $serialEq === $this->normalizar($m->serial)) {
            return true;
        }

        $claveEq = $this->claveActivo($e->activo_fijo);
        if ($claveEq !== '' && $claveEq === $this->claveActivo($m->codigo)) {
            $sedes = array_filter([(int) $e->sede_id, (int) $e->traslado_sede_id]);
            return in_array((int) $m->sede_id, $sedes, true);
        }

        return false;
    }

    private function normalizar(?string $v): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim((string) $v))) ?? '';
    }

    /** "MAQ0007", "MAQ 0007", "0007" y "7" => "7". Devuelve '' si no hay número. */
    private function claveActivo(?string $v): string
    {
        $n = $this->normalizar($v);
        if (!preg_match('/^(?:MAQ)?0*(\d+)$/', $n, $m)) {
            return '';
        }
        return $m[1];
    }
}
