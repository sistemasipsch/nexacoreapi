<?php
namespace App\Modules\Configuracion\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Configuracion\Application\UseCases\Sede\ListarSedeUseCase;
use App\Modules\Configuracion\Application\UseCases\Sede\CrearSedeUseCase;
use App\Modules\Configuracion\Application\UseCases\Sede\ObtenerSedeUseCase;
use App\Modules\Configuracion\Application\UseCases\Sede\ActualizarSedeUseCase;
use App\Modules\Configuracion\Application\UseCases\Sede\EliminarSedeUseCase;
use App\Modules\Configuracion\Application\UseCases\Sede\ListarResponsablesSedeUseCase;
use App\Services\PermissionService;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;

class SedeController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ListarSedeUseCase $listarUseCase,
        protected CrearSedeUseCase $crearUseCase,
        protected ObtenerSedeUseCase $obtenerUseCase,
        protected ActualizarSedeUseCase $actualizarUseCase,
        protected EliminarSedeUseCase $eliminarUseCase
    ) {}

    public function index(Request $request)
    {
        // $this->permissionService->authorize('sede.listar');
        return ApiResponse::success($this->listarUseCase->execute(), 'Lista de Sedes');
    }

    public function store(Request $request)
    {
        $this->permissionService->authorize('sede.crear');
        try {
            return ApiResponse::success($this->crearUseCase->execute($this->datosValidados($request)), 'Sede creado', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error(collect($e->errors())->flatten()->first(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al crear: ' . $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        // $this->permissionService->authorize('sede.listar');
        $item = $this->obtenerUseCase->execute($id);
        if (!$item) return ApiResponse::error('No encontrado', 404);
        return ApiResponse::success($item, 'Detalle de Sede');
    }

    public function update(Request $request, $id)
    {
        $this->permissionService->authorize('sede.actualizar');
        try {
            $item = $this->actualizarUseCase->execute($id, $this->datosValidados($request));
            if (!$item) return ApiResponse::error('No encontrado', 404);
            return ApiResponse::success($item, 'Sede actualizado');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error(collect($e->errors())->flatten()->first(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar: ' . $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        $this->permissionService->authorize('sede.eliminar');
        if ($this->eliminarUseCase->execute($id)) {
            return ApiResponse::success(null, 'Sede eliminado');
        }
        return ApiResponse::error('No encontrado', 404);
    }
    /** Usuarios activos para elegir el responsable de una sede */
    public function responsablesDisponibles(ListarResponsablesSedeUseCase $useCase)
    {
        return ApiResponse::success($useCase->execute(), 'Usuarios disponibles');
    }

    private function datosValidados(Request $request): array
    {
        if ($request->input('responsable_id') === '') {
            $request->merge(['responsable_id' => null]);
        }

        return $request->validate([
            'nombre' => 'required|string|max:100',
            'responsable_id' => 'nullable|integer|exists:usuarios,id',
        ], [
            'nombre.required' => 'El nombre de la sede es obligatorio',
            'responsable_id.exists' => 'El responsable seleccionado no existe',
        ]);
    }
}