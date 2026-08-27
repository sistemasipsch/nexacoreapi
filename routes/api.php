<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'api' => 'pure',
        'laravel' => 12
    ]);
});

// Serve storage files through API route
Route::get('storage/{path}', function (string $path) {
    $cleanPath = ltrim(str_replace(['storage/', 'public/'], '', $path), '/');
    $absolutePath = storage_path('app/public/' . $cleanPath);

    if (file_exists($absolutePath)) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            return response()->file($absolutePath);
        }
        abort(403, 'Extension not allowed');
    }
    abort(404, 'File not found');
})->where('path', '.*');

\Illuminate\Support\Facades\Broadcast::routes(['middleware' => ['auth:api']]);

// TEMPORARY DEBUG - REMOVE AFTER FIXING
Route::post('/debug-login', function (\Illuminate\Http\Request $request) {
    $usuario = $request->input('usuario');
    $contrasena = $request->input('contrasena');

    $user = \App\Models\Usuario::where('usuario', $usuario)->first();

    if (!$user) {
        return response()->json(['error' => 'User not found', 'searched_by' => $usuario]);
    }

    $rawHash = $user->getAttributes()['contrasena'] ?? 'NULL';
    $isBcrypt = str_starts_with($rawHash, '$2y$') || str_starts_with($rawHash, '$2a$');
    $hashLength = strlen($rawHash);
    $hashCheck = \Illuminate\Support\Facades\Hash::check($contrasena, $rawHash);

    return response()->json([
        'user_found' => true,
        'user_id' => $user->id,
        'hash_starts_with' => substr($rawHash, 0, 7),
        'hash_length' => $hashLength,
        'is_bcrypt_format' => $isBcrypt,
        'hash_check_result' => $hashCheck,
        'getAuthPassword_works' => $user->getAuthPassword() !== null,
    ]);
});


Route::group(['middleware' => 'api'], function () {

    // Inventario Routes (protected with JWT authentication)
    Route::middleware(['auth:api', 'activity'])->group(function () {
        // Activity heartbeat moved to Autenticacion

        Route::get('/inventario/by-responsable-coordinador', [App\Modules\GestionCompras\Presentation\Controllers\CpInventarioController::class, 'getByResponsableAndCoordinador']);
        Route::get('inventario', [App\Modules\GestionCompras\Presentation\Controllers\CpInventarioController::class, 'index']);
        Route::get('inventario/{id}', [App\Modules\GestionCompras\Presentation\Controllers\CpInventarioController::class, 'show']);
        Route::post('inventario', [App\Modules\GestionCompras\Presentation\Controllers\CpInventarioController::class, 'store']);
        Route::put('inventario/{id}', [App\Modules\GestionCompras\Presentation\Controllers\CpInventarioController::class, 'update']);
        Route::delete('inventario/{id}', [App\Modules\GestionCompras\Presentation\Controllers\CpInventarioController::class, 'destroy']);

        // Roles Routes
        Route::apiResource('roles', App\Modules\Configuracion\Presentation\Controllers\RolController::class);
        Route::put('roles/{id}/permissions', [App\Modules\Configuracion\Presentation\Controllers\RolController::class, 'assignPermissions']);

        // CP Tables Routes
        Route::apiResource('datos-empresa', App\Modules\Configuracion\Presentation\Controllers\DatosEmpresaController::class);

        // Sedes, Dependencias and Areas Routes
        Route::apiResource('sedes', App\Modules\Configuracion\Presentation\Controllers\SedeController::class);
        Route::apiResource('dependencias-sedes', App\Modules\Configuracion\Presentation\Controllers\DependenciaSedeController::class);
        Route::apiResource('areas', App\Modules\Configuracion\Presentation\Controllers\AreaController::class);

        // Personal and Cargo Routes
        Route::apiResource('p-cargos', App\Modules\Configuracion\Presentation\Controllers\PCargoController::class);
        Route::get('personal/buscar', [App\Modules\Configuracion\Presentation\Controllers\PersonalController::class, 'buscar']);
        Route::get('personal/buscar-externo', [App\Modules\Configuracion\Presentation\Controllers\PersonalController::class, 'buscarExterno']);
        Route::match(['put', 'post'], 'personal/{id}', [App\Modules\Configuracion\Presentation\Controllers\PersonalController::class, 'update']);
        Route::apiResource('personal', App\Modules\Configuracion\Presentation\Controllers\PersonalController::class);

        // Dashboard Stats moved to Dashboard module

        // Profile Routes moved to Autenticacion module

        // Permisos Routes
        Route::apiResource('permisos', App\Modules\Configuracion\Presentation\Controllers\PermisoController::class);
        Route::get('permisos/roles-assignments/list', [App\Modules\Configuracion\Presentation\Controllers\PermisoController::class, 'getRoles']);
        Route::post('permisos/assign', [App\Modules\Configuracion\Presentation\Controllers\PermisoController::class, 'assignPermisos']);

        // Mantenimientos Routes
        Route::get('mantenimientos/mis-mantenimientos', [App\Modules\GestionInfraestructura\Presentation\Controllers\MantenimientoController::class, 'misMantenimientos']);
        Route::get('mantenimientos/exportar-excel', [App\Modules\GestionInfraestructura\Presentation\Controllers\MantenimientoController::class, 'exportExcel']);
        Route::get('mantenimientos/mis-mantenimientos/exportar-excel', [App\Modules\GestionInfraestructura\Presentation\Controllers\MantenimientoController::class, 'exportMisMantenimientosExcel']);
        Route::get('mantenimientos/tecnico/{tecnico_id}/exportar-excel', [App\Modules\GestionInfraestructura\Presentation\Controllers\MantenimientoController::class, 'exportMantenimientosTecnicoExcel']);
        Route::get('mantenimientos/estadisticas', [App\Modules\GestionInfraestructura\Presentation\Controllers\MantenimientoController::class, 'getStatistics']);
        Route::apiResource('mantenimientos', App\Modules\GestionInfraestructura\Presentation\Controllers\MantenimientoController::class);
        Route::post('mantenimientos/{id}/marcar-revisado', [App\Modules\GestionInfraestructura\Presentation\Controllers\MantenimientoController::class, 'marcarRevisado']);

        // Usuarios por permiso route moved to Autenticacion/Presentation/Routes/api.php

        // Agenda Mantenimientos Routes
        Route::get('agenda-mantenimientos/disponibilidad', [App\Modules\GestionInfraestructura\Presentation\Controllers\AgendaMantenimientoController::class, 'getDisponibilidad']);
        Route::get('agenda-mantenimientos/mantenimiento/{mantenimiento_id}', [App\Modules\GestionInfraestructura\Presentation\Controllers\AgendaMantenimientoController::class, 'getByMantenimiento']);
        Route::apiResource('agenda-mantenimientos', App\Modules\GestionInfraestructura\Presentation\Controllers\AgendaMantenimientoController::class);
    });
});

// Rutas del Dominio: Buzón de Sugerencias
require base_path('app/Modules/BuzonSugerencias/Presentation/Routes/api.php');

// Rutas del Dominio: Gestión de Sistemas
require base_path('app/Modules/GestionSistemas/Presentation/Routes/api.php');

// Rutas del Dominio: Gestión de Compras
require base_path('app/Modules/GestionCompras/Presentation/Routes/api.php');

// Rutas del Dominio: Autenticación
require base_path('app/Modules/Autenticacion/Presentation/Routes/api.php');

// Rutas del Dominio: Dashboard
require base_path('app/Modules/Dashboard/Presentation/Routes/api.php');
