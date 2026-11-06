<?php

namespace App\Modules\GestionSistemas\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PcPrestamo;
use App\Models\PcEquipo;
use App\Models\Personal;
use App\Responses\ApiResponse;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PcPrestamoController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    public function index(Request $request)
    {
        $this->permissionService->authorize('pc_prestamo.listar');

        $query = PcPrestamo::with([
            'equipo:id,nombre_equipo,marca,modelo,serial,tipo',
            'sede:id,nombre',
            'prestadoPorUsuario:id,nombre_completo',
            'recibidoPorUsuario:id,nombre_completo',
            'titularPersona:id,nombre,cargo_id',
            'prestatarioPersona:id,nombre,cargo_id',
        ]);

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        if ($request->filled('buscar')) {
            $term = trim($request->buscar);
            $query->where(function ($q) use ($term) {
                $q->where('descripcion_equipo', 'like', "%{$term}%")
                  ->orWhere('serial', 'like', "%{$term}%")
                  ->orWhere('titular_nombre', 'like', "%{$term}%")
                  ->orWhere('prestatario_nombre', 'like', "%{$term}%");
            });
        }

        $prestamos = $query->orderBy('fecha_prestamo', 'desc')->get();

        return ApiResponse::success($prestamos, 'Listado de préstamos obtenido correctamente');
    }

    /**
     * Búsqueda unificada por serial / nombre / marca / código en:
     *  - pc_equipos (equipos de cómputo)
     *  - inventario_infraestructura (equipos biomédicos / infraestructura)
     *  - inventario (periféricos y demás activos)
     */
    public function buscarEquipo(Request $request)
    {
        $this->permissionService->authorize('pc_prestamo.listar');

        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return ApiResponse::success([], 'Ingrese al menos 2 caracteres');
        }
        $like = "%{$term}%";
        $results = [];

        $pc = PcEquipo::with('responsable:id,nombre,cedula,cargo_id')
            ->where(function ($q) use ($like) {
                $q->where('serial', 'like', $like)
                  ->orWhere('nombre_equipo', 'like', $like)
                  ->orWhere('marca', 'like', $like)
                  ->orWhere('modelo', 'like', $like)
                  ->orWhere('numero_inventario', 'like', $like);
            })->limit(15)->get();
        foreach ($pc as $e) {
            $results[] = [
                'origen' => 'computo',
                'origen_label' => 'Equipo de cómputo',
                'id' => $e->id,
                'equipo_id' => $e->id,
                'nombre' => $e->nombre_equipo ?: trim(($e->tipo ?? 'Equipo') . ' ' . $e->marca),
                'marca' => $e->marca,
                'modelo' => $e->modelo,
                'serial' => $e->serial,
                'codigo' => $e->numero_inventario,
                'responsable' => $e->responsable,
            ];
        }

        $bio = DB::table('inventario_infraestructura')
            ->where(function ($q) use ($like) {
                $q->where('serial', 'like', $like)
                  ->orWhere('nombre_equipo', 'like', $like)
                  ->orWhere('marca', 'like', $like)
                  ->orWhere('modelo', 'like', $like)
                  ->orWhere('activo_fijo', 'like', $like);
            })->limit(15)->get(['id', 'nombre_equipo', 'tipo_equipo', 'marca', 'modelo', 'serial', 'activo_fijo']);
        foreach ($bio as $e) {
            $results[] = [
                'origen' => 'biomedico',
                'origen_label' => 'Equipo biomédico / infraestructura',
                'id' => $e->id,
                'equipo_id' => null,
                'nombre' => $e->nombre_equipo ?: $e->tipo_equipo,
                'marca' => $e->marca,
                'modelo' => $e->modelo,
                'serial' => $e->serial,
                'codigo' => $e->activo_fijo,
                'responsable' => null,
            ];
        }

        $inv = DB::table('inventario')
            ->where(function ($q) use ($like) {
                $q->where('serial', 'like', $like)
                  ->orWhere('nombre', 'like', $like)
                  ->orWhere('marca', 'like', $like)
                  ->orWhere('modelo', 'like', $like)
                  ->orWhere('codigo', 'like', $like)
                  ->orWhere('codigo_barras', 'like', $like);
            })->limit(15)->get(['id', 'codigo', 'nombre', 'marca', 'modelo', 'serial']);
        foreach ($inv as $e) {
            $results[] = [
                'origen' => 'inventario',
                'origen_label' => 'Inventario (periféricos y otros)',
                'id' => $e->id,
                'equipo_id' => null,
                'nombre' => $e->nombre,
                'marca' => $e->marca,
                'modelo' => $e->modelo,
                'serial' => $e->serial,
                'codigo' => $e->codigo,
                'responsable' => null,
            ];
        }

        return ApiResponse::success($results, 'Resultados de búsqueda');
    }

    public function store(Request $request)
    {
        $this->permissionService->authorize('pc_prestamo.crear');

        $validated = $request->validate([
            'equipo_id' => 'nullable|exists:pc_equipos,id',
            'descripcion_equipo' => 'required|string|max:500',
            'serial' => 'nullable|string|max:150',
            'titular_nombre' => 'required|string|max:255',
            'titular_persona_id' => 'nullable|exists:personal,id',
            'prestatario_nombre' => 'required|string|max:255',
            'prestatario_persona_id' => 'nullable|exists:personal,id',
            'sede_id' => 'nullable|exists:sedes,id',
            'fecha_prestamo' => 'required|date',
            'fecha_devolucion_esperada' => 'nullable|date',
            'observaciones' => 'nullable|string',
        ]);

        $user = auth('api')->user() ?? auth()->user();

        // Si se seleccionó equipo_id pero no serial, heredar serial del equipo
        if (!empty($validated['equipo_id']) && empty($validated['serial'])) {
            $equipo = PcEquipo::find($validated['equipo_id']);
            if ($equipo && $equipo->serial) {
                $validated['serial'] = $equipo->serial;
            }
        }

        $prestamo = PcPrestamo::create([
            ...$validated,
            'prestado_por' => $user->id,
            'estado' => 'vigente',
        ]);

        $prestamo->load(['equipo', 'sede', 'prestadoPorUsuario']);

        return ApiResponse::success($prestamo, 'Préstamo registrado exitosamente', 201);
    }

    public function show($id)
    {
        $this->permissionService->authorize('pc_prestamo.listar');

        $prestamo = PcPrestamo::with([
            'equipo',
            'sede',
            'prestadoPorUsuario:id,nombre_completo',
            'recibidoPorUsuario:id,nombre_completo',
            'titularPersona',
            'prestatarioPersona',
        ])->find($id);

        if (!$prestamo) {
            return ApiResponse::error('Préstamo no encontrado', 404);
        }

        return ApiResponse::success($prestamo, 'Detalle del préstamo obtenido correctamente');
    }

    public function update($id, Request $request)
    {
        $this->permissionService->authorize('pc_prestamo.actualizar');

        $prestamo = PcPrestamo::find($id);
        if (!$prestamo) {
            return ApiResponse::error('Préstamo no encontrado', 404);
        }

        $validated = $request->validate([
            'equipo_id' => 'nullable|exists:pc_equipos,id',
            'descripcion_equipo' => 'required|string|max:500',
            'serial' => 'nullable|string|max:150',
            'titular_nombre' => 'required|string|max:255',
            'titular_persona_id' => 'nullable|exists:personal,id',
            'prestatario_nombre' => 'required|string|max:255',
            'prestatario_persona_id' => 'nullable|exists:personal,id',
            'sede_id' => 'nullable|exists:sedes,id',
            'fecha_prestamo' => 'required|date',
            'fecha_devolucion_esperada' => 'nullable|date',
            'observaciones' => 'nullable|string',
            'estado' => 'nullable|string|in:vigente,devuelto',
        ]);

        $prestamo->update($validated);
        $prestamo->load(['equipo', 'sede', 'prestadoPorUsuario', 'recibidoPorUsuario']);

        return ApiResponse::success($prestamo, 'Préstamo actualizado exitosamente');
    }

    public function marcarDevuelto($id, Request $request)
    {
        $this->permissionService->authorize('pc_prestamo.actualizar');

        $prestamo = PcPrestamo::find($id);
        if (!$prestamo) {
            return ApiResponse::error('Préstamo no encontrado', 404);
        }

        $user = auth('api')->user() ?? auth()->user();

        $validated = $request->validate([
            'fecha_devolucion_real' => 'nullable|date',
            'observaciones_devolucion' => 'nullable|string',
        ]);

        $fechaDevolucion = !empty($validated['fecha_devolucion_real'])
            ? Carbon::parse($validated['fecha_devolucion_real'])
            : Carbon::now('America/Bogota');

        $prestamo->update([
            'estado' => 'devuelto',
            'fecha_devolucion_real' => $fechaDevolucion,
            'recibido_por' => $user->id,
            'observaciones_devolucion' => $validated['observaciones_devolucion'] ?? $prestamo->observaciones_devolucion,
        ]);

        $prestamo->load(['equipo', 'sede', 'prestadoPorUsuario', 'recibidoPorUsuario']);

        return ApiResponse::success($prestamo, 'Equipo marcado como devuelto exitosamente');
    }

    public function destroy($id)
    {
        $this->permissionService->authorize('pc_prestamo.eliminar');

        $prestamo = PcPrestamo::find($id);
        if (!$prestamo) {
            return ApiResponse::error('Préstamo no encontrado', 404);
        }

        $prestamo->delete();

        return ApiResponse::success(null, 'Registro de préstamo eliminado exitosamente');
    }
}
