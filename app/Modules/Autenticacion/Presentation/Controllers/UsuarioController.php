<?php

namespace App\Modules\Autenticacion\Presentation\Controllers;

use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

use App\Modules\Autenticacion\Application\UseCases\Usuario\ListarUsuariosUseCase;
use App\Modules\Autenticacion\Application\UseCases\Usuario\ObtenerUsuarioUseCase;
use App\Modules\Autenticacion\Application\UseCases\Usuario\CrearUsuarioUseCase;
use App\Modules\Autenticacion\Application\UseCases\Usuario\ActualizarUsuarioUseCase;
use App\Modules\Autenticacion\Application\UseCases\Usuario\EliminarUsuarioUseCase;
use App\Modules\Autenticacion\Application\UseCases\Usuario\ListarUsuariosPorPermisoUseCase;

class UsuarioController extends Controller
{
    public function __construct(
        protected ListarUsuariosUseCase $listarUsuariosUseCase,
        protected ObtenerUsuarioUseCase $obtenerUsuarioUseCase,
        protected CrearUsuarioUseCase $crearUsuarioUseCase,
        protected ActualizarUsuarioUseCase $actualizarUsuarioUseCase,
        protected EliminarUsuarioUseCase $eliminarUsuarioUseCase,
        protected ListarUsuariosPorPermisoUseCase $listarUsuariosPorPermisoUseCase
    ) {}

    #[OA\Get(
        path: '/api/usuarios',
        tags: ['Usuarios'],
        summary: 'Listar usuarios',
        description: 'Obtiene una lista de todos los usuarios registrados.',
        operationId: 'usuariosIndex',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de usuarios',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ApiResponse',
                    example: [
                        'mensaje' => 'Lista de usuarios obtenida exitosamente',
                        'objeto' => [
                            ['id' => 1, 'usuario' => 'admin', 'rol' => []]
                        ],
                        'status' => 200
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            )
        ]
    )]
    public function index()
    {
        $usuarios = $this->listarUsuariosUseCase->execute();
        return ApiResponse::success($usuarios, 'Lista de usuarios obtenida exitosamente');
    }

    public function show($id)
    {
        try {
            $usuario = $this->obtenerUsuarioUseCase->execute($id);
            return ApiResponse::success($usuario, 'Usuario obtenido exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Usuario no encontrado', 404);
        }
    }

    #[OA\Post(
        path: '/api/usuarios',
        tags: ['Usuarios'],
        summary: 'Crear usuario',
        description: 'Registra un nuevo usuario en el sistema.',
        operationId: 'usuariosStore',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['usuario', 'contrasena'],
                properties: [
                    new OA\Property(property: 'nombre_completo', type: 'string', example: 'Juan Perez'),
                    new OA\Property(property: 'usuario', type: 'string', example: 'juan.perez'),
                    new OA\Property(property: 'contrasena', type: 'string', format: 'password', example: 'secret123'),
                    new OA\Property(property: 'correo', type: 'string', format: 'email', example: 'juan@example.com'),
                    new OA\Property(property: 'rol_id', type: 'integer', example: 1),
                    new OA\Property(property: 'sede_id', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Usuario creado',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(
                response: 422,
                description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            )
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_completo' => 'required|string|min:6|max:100',
            'usuario' => ['required', 'string', 'min:3', 'max:50', 'regex:/@/', 'unique:usuarios,usuario'],
            'contrasena' => 'required|string|min:6',
            'rol_id' => 'required|exists:rol,id',
            'correo' => 'required|email|max:60',
            'telefono' => ['required', 'string', 'regex:/^\d{10,15}$/'],
            'estado' => 'boolean',
            'sede_id' => 'required|exists:sedes,id',
            'firma_digital' => 'nullable',
        ], [
            'nombre_completo.required' => 'El nombre completo (nombres y apellidos) es obligatorio.',
            'nombre_completo.min' => 'El nombre completo debe tener al menos 6 caracteres (nombres y apellidos).',
            'usuario.required' => 'El identificador de usuario es obligatorio.',
            'usuario.regex' => 'El identificador de usuario debe incluir un "@" (ej. usuario@house).',
            'usuario.unique' => 'El nombre de usuario ya está registrado en el sistema.',
            'contrasena.required' => 'La contraseña es obligatoria para el nuevo usuario.',
            'contrasena.min' => 'La contraseña debe contener al menos 6 caracteres.',
            'rol_id.required' => 'Debe asignar un rol al usuario.',
            'correo.required' => 'El correo electrónico es obligatorio.',
            'correo.email' => 'El formato del correo electrónico no es válido.',
            'telefono.required' => 'El teléfono de contacto es obligatorio.',
            'telefono.regex' => 'El teléfono debe contener únicamente números (entre 10 y 15 dígitos, sin puntos ni caracteres especiales).',
            'sede_id.required' => 'Debe asignar una sede al usuario.',
        ]);

        // Normalización de mayúsculas y minúsculas
        $validated['nombre_completo'] = mb_strtoupper(trim($validated['nombre_completo']), 'UTF-8');
        $validated['usuario'] = strtolower(trim($validated['usuario']));
        $validated['correo'] = strtolower(trim($validated['correo']));
        $validated['telefono'] = preg_replace('/\D/', '', $validated['telefono']);

        // Manejo y validación de firma obligatoria en creación
        if ($request->hasFile('firma_digital')) {
            $validated['firma_file'] = $request->file('firma_digital');
        } elseif ($request->filled('firma_digital') && is_string($request->input('firma_digital')) && str_starts_with($request->input('firma_digital'), 'data:image')) {
            $validated['firma_digital'] = $request->input('firma_digital');
        } elseif ($request->filled('firma') && is_string($request->input('firma')) && str_starts_with($request->input('firma'), 'data:image')) {
            $validated['firma_digital'] = $request->input('firma');
        } else {
            return ApiResponse::error('La firma digital es obligatoria para registrar al usuario. Puede dibujarla o subir una imagen.', 422);
        }

        $usuario = $this->crearUsuarioUseCase->execute($validated);
        if ($usuario) {
            self::sincronizarSedePersona($usuario);
        }
        return ApiResponse::success($usuario, 'Usuario creado exitosamente', 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nombre_completo' => 'sometimes|required|string|min:6|max:100',
            'usuario' => ['sometimes', 'required', 'string', 'min:3', 'max:50', 'regex:/@/', Rule::unique('usuarios')->ignore($id)],
            'contrasena' => 'nullable|string|min:6',
            'rol_id' => 'sometimes|required|exists:rol,id',
            'correo' => 'sometimes|required|email|max:60',
            'telefono' => ['sometimes', 'required', 'string', 'regex:/^\d{10,15}$/'],
            'estado' => 'sometimes|boolean',
            'sede_id' => 'sometimes|required|exists:sedes,id',
            'firma_digital' => 'nullable',
        ], [
            'nombre_completo.required' => 'El nombre completo es obligatorio.',
            'nombre_completo.min' => 'El nombre completo debe tener al menos 6 caracteres (nombres y apellidos).',
            'usuario.required' => 'El identificador de usuario es obligatorio.',
            'usuario.regex' => 'El identificador de usuario debe incluir un "@" (ej. usuario@house).',
            'usuario.unique' => 'El nombre de usuario ya está en uso.',
            'rol_id.required' => 'Debe asignar un rol al usuario.',
            'correo.required' => 'El correo electrónico es obligatorio.',
            'correo.email' => 'El formato del correo electrónico no es válido.',
            'telefono.required' => 'El teléfono de contacto es obligatorio.',
            'telefono.regex' => 'El teléfono debe contener únicamente números (entre 10 y 15 dígitos, sin puntos ni caracteres especiales).',
            'sede_id.required' => 'Debe asignar una sede al usuario.',
        ]);

        if (isset($validated['nombre_completo'])) {
            $validated['nombre_completo'] = mb_strtoupper(trim($validated['nombre_completo']), 'UTF-8');
        }
        if (isset($validated['usuario'])) {
            $validated['usuario'] = strtolower(trim($validated['usuario']));
        }
        if (isset($validated['correo'])) {
            $validated['correo'] = strtolower(trim($validated['correo']));
        }
        if (isset($validated['telefono'])) {
            $validated['telefono'] = preg_replace('/\D/', '', $validated['telefono']);
        }

        if ($request->hasFile('firma_digital')) {
            $validated['firma_file'] = $request->file('firma_digital');
        } elseif ($request->filled('firma_digital') && is_string($request->input('firma_digital')) && str_starts_with($request->input('firma_digital'), 'data:image')) {
            $validated['firma_digital'] = $request->input('firma_digital');
        } elseif ($request->filled('firma') && is_string($request->input('firma')) && str_starts_with($request->input('firma'), 'data:image')) {
            $validated['firma_digital'] = $request->input('firma');
        }

        try {
            $usuario = $this->actualizarUsuarioUseCase->execute($id, $validated);
            if ($usuario) {
                self::sincronizarSedePersona($usuario);
            }
            return ApiResponse::success($usuario, 'Usuario actualizado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar usuario: ' . $e->getMessage(), 400);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $forceDeactivate = $request->boolean('desactivar');
            $resultado = $this->eliminarUsuarioUseCase->execute($id, $forceDeactivate);

            return ApiResponse::success($resultado, $resultado['mensaje'] ?? 'Operación realizada exitosamente');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Usuario no encontrado', 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    public function getByPermission($permiso)
    {
        $usuarios = $this->listarUsuariosPorPermisoUseCase->execute($permiso);
        return ApiResponse::success($usuarios, 'Usuarios con permiso: ' . $permiso);
    }

    public static function sincronizarSedePersona($usuario)
    {
        try {
            $clean = function($str) {
                $str = mb_strtoupper(trim($str), 'UTF-8');
                $unwanted = ['Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U', 'Ü'=>'U', 'Ñ'=>'N'];
                return strtr($str, $unwanted);
            };

            $uClean = $clean($usuario->nombre_completo ?? '');
            if (!$uClean) return;

            $uWords = array_values(array_filter(explode(' ', $uClean), fn($w) => strlen($w) >= 3));

            $personales = \App\Models\Personal::all();
            foreach ($personales as $p) {
                $pClean = $clean($p->nombre);
                $isMatch = false;
                if ($pClean === $uClean) {
                    $isMatch = true;
                } else {
                    $pWords = array_values(array_filter(explode(' ', $pClean), fn($w) => strlen($w) >= 3));
                    $intersect = array_intersect($uWords, $pWords);
                    if (count($intersect) >= 2 && count($intersect) >= count($uWords) * 0.5) {
                        $isMatch = true;
                    }
                }

                if ($isMatch) {
                    $p->update(['sede_id' => $usuario->sede_id]);
                    \App\Models\CpEntregaActivosFijos::where('personal_id', $p->id)
                        ->update(['sede_id' => $usuario->sede_id]);
                }
            }
        } catch (\Exception $e) {
            // Silencioso para no interrumpir el flujo principal de usuarios
        }
    }
}
