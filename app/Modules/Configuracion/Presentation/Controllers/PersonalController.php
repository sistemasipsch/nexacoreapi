<?php
namespace App\Modules\Configuracion\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Configuracion\Application\UseCases\Personal\ListarPersonalUseCase;
use App\Modules\Configuracion\Application\UseCases\Personal\CrearPersonalUseCase;
use App\Modules\Configuracion\Application\UseCases\Personal\ObtenerPersonalUseCase;
use App\Modules\Configuracion\Application\UseCases\Personal\ActualizarPersonalUseCase;
use App\Modules\Configuracion\Application\UseCases\Personal\EliminarPersonalUseCase;
use App\Services\PermissionService;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;

class PersonalController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ListarPersonalUseCase $listarUseCase,
        protected CrearPersonalUseCase $crearUseCase,
        protected ObtenerPersonalUseCase $obtenerUseCase,
        protected ActualizarPersonalUseCase $actualizarUseCase,
        protected EliminarPersonalUseCase $eliminarUseCase
    ) {}

    public function index(Request $request)
    {
        $this->permissionService->authorize('personal.listar');
        return ApiResponse::success($this->listarUseCase->execute(), 'Lista de Personal');
    }

    public function store(Request $request)
    {
        $this->permissionService->authorize('personal.crear');
        try {
            $data = $request->all();
            if ($request->hasFile('firma_file')) {
                $data['firma_file'] = $request->file('firma_file');
            } elseif ($request->hasFile('firma')) {
                $data['firma_file'] = $request->file('firma');
            }
            return ApiResponse::success($this->crearUseCase->execute($data), 'Personal creado', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al crear: ' . $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        $this->permissionService->authorize('personal.listar');
        $item = $this->obtenerUseCase->execute($id);
        if (!$item) return ApiResponse::error('No encontrado', 404);
        return ApiResponse::success($item, 'Detalle de Personal');
    }

    public function update(Request $request, $id)
    {
        $this->permissionService->authorize('personal.actualizar');
        try {
            $data = $request->all();
            if ($request->hasFile('firma_file')) {
                $data['firma_file'] = $request->file('firma_file');
            } elseif ($request->hasFile('firma')) {
                $data['firma_file'] = $request->file('firma');
            }
            $item = $this->actualizarUseCase->execute($id, $data);
            if (!$item) return ApiResponse::error('No encontrado', 404);
            return ApiResponse::success($item, 'Personal actualizado');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar: ' . $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        $this->permissionService->authorize('personal.eliminar');
        if ($this->eliminarUseCase->execute($id)) {
            return ApiResponse::success(null, 'Personal eliminado');
        }
        return ApiResponse::error('No encontrado', 404);
    }

    public function buscar(Request $request)
    {
        $termino = $request->input('termino') ?? $request->input('q');
        if (!$termino) return ApiResponse::success([], 'Término no proporcionado', 200);

        // Simulando busqueda en caso de uso o modelo directo para simplificar
        $resultados = \App\Models\Personal::where('cedula', 'like', "%$termino%")->orWhere('nombre', 'like', "%$termino%")->get();
        return ApiResponse::success($resultados, 'Resultados locales');
    }

    public function buscarExterno(Request $request, \App\Modules\Gateway\Application\UseCases\BuscarTercerosGatewayUseCase $buscarTercerosUseCase)
    {
        $termino = $request->input('termino') ?? $request->input('q');
        if (!$termino) return ApiResponse::error('Término requerido', 400);

        $kubappResults = $buscarTercerosUseCase->execute($termino);
        
        $synced = collect();
        if ($kubappResults->isNotEmpty()) {
            foreach ($kubappResults as $tercero) {
                if (empty($tercero->nit) || empty($tercero->nombre)) continue;
                try {
                    $personal = \App\Models\Personal::firstOrCreate(
                        ['cedula' => $tercero->nit],
                        ['nombre' => $tercero->nombre, 'telefono' => null, 'cargo_id' => null]
                    );
                    $personal->load('cargo');
                    $synced->push($personal);
                } catch (\Exception $e) { }
            }
        }
        return ApiResponse::success($synced, 'Resultados externos sincronizados');
    }
}