<?php

namespace App\Modules\GestionSistemas\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PcEquipo;
use App\Models\Personal;
use App\Models\Usuario;
use App\Responses\ApiResponse;
use App\Services\PermissionService;
use Illuminate\Http\Request;

class PcEquiposACargoController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Resuelve el registro de Personal correspondiente a un usuario
     */
    protected function resolvePersonalForUser(?Usuario $user): ?Personal
    {
        if (!$user) {
            return null;
        }

        // 1. Coincidencia exacta de nombre
        if (!empty($user->nombre_completo)) {
            $p = Personal::with(['cargo', 'sede'])
                ->where('nombre', trim($user->nombre_completo))
                ->first();
            if ($p) {
                return $p;
            }
        }

        // 2. Coincidencia por teléfono (si tiene longitud válida)
        if (!empty($user->telefono) && strlen(trim($user->telefono)) >= 7) {
            $p = Personal::with(['cargo', 'sede'])
                ->where('telefono', trim($user->telefono))
                ->first();
            if ($p) {
                return $p;
            }
        }

        // 3. Coincidencia normalizada sin tildes, mayúsculas ni dobles espacios
        if (!empty($user->nombre_completo)) {
            $clean = function ($str) {
                $str = mb_strtoupper(trim($str ?? ''), 'UTF-8');
                $unwanted = ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N'];
                return preg_replace('/\s+/', ' ', strtr($str, $unwanted));
            };

            $uClean = $clean($user->nombre_completo);
            $uWords = array_values(array_filter(explode(' ', $uClean), fn($w) => strlen($w) >= 3));

            $personales = Personal::with(['cargo', 'sede'])->get();
            foreach ($personales as $p) {
                $pClean = $clean($p->nombre);
                if ($pClean === $uClean) {
                    return $p;
                }

                $pWords = array_values(array_filter(explode(' ', $pClean), fn($w) => strlen($w) >= 3));
                $intersect = array_intersect($uWords, $pWords);
                // Si coinciden al menos 2 palabras clave y representan el 50% o más
                if (count($intersect) >= 2 && count($intersect) >= (count($uWords) * 0.5)) {
                    return $p;
                }
            }
        }

        return null;
    }

    public function index(Request $request)
    {
        $user = auth('api')->user() ?? auth()->user();
        if (!$user) {
            return ApiResponse::unauthorized('Usuario no autenticado.');
        }

        $canViewAny = $this->permissionService->check($user, 'pc_equipo.listar');

        $targetPersonalId = null;
        $targetPersona = null;

        // Solo si tiene permiso especial de Sistemas/Admin Y especifica persona_id o usuario_id se consulta otra persona
        if ($request->filled('persona_id') && $canViewAny) {
            $targetPersonalId = (int) $request->persona_id;
            $targetPersona = Personal::with(['cargo', 'sede'])->find($targetPersonalId);
        } elseif ($request->filled('usuario_id') && $canViewAny) {
            $u = Usuario::find($request->usuario_id);
            if ($u) {
                $targetPersona = $this->resolvePersonalForUser($u);
                $targetPersonalId = $targetPersona?->id;
            }
        } else {
            // Usuario actual: ÚNICAMENTE los equipos que tiene esa persona a cargo
            $targetPersona = $this->resolvePersonalForUser($user);
            $targetPersonalId = $targetPersona?->id;
        }

        // Si el usuario no tiene registro de personal asociado, no tiene equipos a cargo
        if (!$targetPersonalId) {
            return ApiResponse::success([
                'persona' => [
                    'id' => null,
                    'nombre' => $user->nombre_completo ?? $user->usuario,
                    'cargo' => 'Usuario',
                    'sede' => $user->sede?->nombre ?? 'N/A',
                    'cedula' => null
                ],
                'equipos' => [],
                'total' => 0
            ], 'El usuario no tiene equipos a cargo');
        }

        // Query equipos estrictamente asignados a esa persona
        $query = PcEquipo::with([
            'sede:id,nombre',
            'area:id,nombre',
            'responsable.cargo',
            'responsable.sede',
            'caracteristicasTecnicas',
            'licenciasSoftware',
            'entregas' => fn($q) => $q->whereNull('devuelto')->latest('fecha_entrega'),
        ]);

        // Filtrar donde es responsable directo O tiene un acta de entrega vigente (devuelto is null)
        $query->where(function ($q) use ($targetPersonalId) {
            $q->where('responsable_id', $targetPersonalId)
              ->orWhereHas('entregas', function ($sub) use ($targetPersonalId) {
                  $sub->where('funcionario_id', $targetPersonalId)
                      ->whereNull('devuelto');
              });
        });

        // Filtro opcional de búsqueda rápida del usuario (serial, nombre_equipo, modelo, inventario, marca)
        if ($request->filled('buscar')) {
            $term = trim($request->buscar);
            $query->where(function ($q) use ($term) {
                $q->where('nombre_equipo', 'like', "%{$term}%")
                  ->orWhere('serial', 'like', "%{$term}%")
                  ->orWhere('modelo', 'like', "%{$term}%")
                  ->orWhere('marca', 'like', "%{$term}%")
                  ->orWhere('numero_inventario', 'like', "%{$term}%");
            });
        }

        $equipos = $query->orderBy('nombre_equipo', 'asc')->get();

        return ApiResponse::success([
            'persona' => $targetPersona ? [
                'id' => $targetPersona->id,
                'nombre' => $targetPersona->nombre,
                'cargo' => $targetPersona->cargo?->nombre ?? 'Funcionario',
                'sede' => $targetPersona->sede?->nombre ?? 'N/A',
                'cedula' => $targetPersona->cedula,
            ] : null,
            'equipos' => $equipos,
            'total' => $equipos->count()
        ], $equipos->isEmpty() ? 'El usuario no tiene equipos a cargo' : 'Equipos a cargo obtenidos correctamente');
    }
}
