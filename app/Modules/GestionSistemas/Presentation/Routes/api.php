<?php

use Illuminate\Support\Facades\Route;
use App\Modules\GestionSistemas\Presentation\Controllers\ActaEntregaController;
use App\Modules\GestionSistemas\Presentation\Controllers\ActaDevolucionController;

Route::middleware('auth:api')->prefix('gestion-sistemas')->group(function () {
    // Actas de Entrega
    Route::get('/actas-entrega', [ActaEntregaController::class, 'index']);
    Route::post('/actas-entrega', [ActaEntregaController::class, 'store']);
    Route::post('/actas-entrega/sincronizar-firmas-admin', [ActaEntregaController::class, 'sincronizarFirmasAdmin']);
    Route::get('/actas-entrega/{id}', [ActaEntregaController::class, 'show']);
    Route::get('/actas-entrega/{id}/exportar-excel', [ActaEntregaController::class, 'exportExcel']);
    Route::get('/actas-entrega/{id}/exportar-pdf', [ActaEntregaController::class, 'exportPdf']);
    Route::match(['put', 'post'], '/actas-entrega/{id}', [ActaEntregaController::class, 'update']);
    Route::delete('/actas-entrega/{id}', [ActaEntregaController::class, 'destroy']);

    // Actas de Devolución
    Route::get('/actas-devolucion', [ActaDevolucionController::class, 'index']);
    Route::post('/actas-devolucion', [ActaDevolucionController::class, 'store']);
    Route::get('/actas-devolucion/{id}', [ActaDevolucionController::class, 'show']);
    Route::get('/actas-devolucion/{id}/exportar-excel', [ActaDevolucionController::class, 'exportExcel']);
    Route::get('/actas-devolucion/{id}/exportar-pdf', [ActaDevolucionController::class, 'exportPdf']);
    Route::delete('/actas-devolucion/{id}', [ActaDevolucionController::class, 'destroy']);

    // PcEquipos
    Route::post('/pc-equipos/auto-recuperar-imagenes', [\App\Modules\GestionSistemas\Presentation\Controllers\PcEquipoController::class, 'autoRecuperarImagenes']);
    Route::get('/pc-equipos/imagen/{filename}', [\App\Modules\GestionSistemas\Presentation\Controllers\PcEquipoController::class, 'servirImagen']);
    Route::get('/pc-equipos/buscar', [\App\Modules\GestionSistemas\Presentation\Controllers\PcEquipoController::class, 'buscar']);
    Route::match(['put', 'post'], '/pc-equipos/{id}', [\App\Modules\GestionSistemas\Presentation\Controllers\PcEquipoController::class, 'update']);
    Route::apiResource('/pc-equipos', \App\Modules\GestionSistemas\Presentation\Controllers\PcEquipoController::class);
    Route::get('/pc-equipos/{id}/hoja-vida', [\App\Modules\GestionSistemas\Presentation\Controllers\PcEquipoHojaVidaController::class, 'show']);
    Route::get('/pc-equipos/{id}/hoja-vida/exportar-excel', [\App\Modules\GestionSistemas\Presentation\Controllers\PcEquipoHojaVidaController::class, 'exportarExcel']);
    Route::get('/pc-equipos/{id}/hoja-vida/exportar-pdf', [\App\Modules\GestionSistemas\Presentation\Controllers\PcEquipoHojaVidaController::class, 'exportarPdf']);

    // Características Técnicas
    Route::get('/pc-caracteristicas-tecnicas/equipo/{equipo_id}', [\App\Modules\GestionSistemas\Presentation\Controllers\PcCaracteristicasTecnicasController::class, 'showByEquipo']);
    Route::apiResource('/pc-caracteristicas-tecnicas', \App\Modules\GestionSistemas\Presentation\Controllers\PcCaracteristicasTecnicasController::class);

    // Licencias de Software
    Route::get('/pc-licencias-software/equipo/{equipo_id}', [\App\Modules\GestionSistemas\Presentation\Controllers\PcLicenciasSoftwareController::class, 'showByEquipo']);
    Route::post('/pc-licencias-software', [\App\Modules\GestionSistemas\Presentation\Controllers\PcLicenciasSoftwareController::class, 'storeOrUpdate']);

    // Mantenimientos
    Route::get('/pc-mantenimientos/cronograma', [\App\Modules\GestionSistemas\Presentation\Controllers\PcMantenimientoController::class, 'cronograma']);
    Route::get('/pc-mantenimientos', [\App\Modules\GestionSistemas\Presentation\Controllers\PcMantenimientoController::class, 'index']);
    Route::get('/pc-mantenimientos/equipo/{equipo_id}', [\App\Modules\GestionSistemas\Presentation\Controllers\PcMantenimientoController::class, 'showByEquipo']);
    Route::post('/pc-mantenimientos', [\App\Modules\GestionSistemas\Presentation\Controllers\PcMantenimientoController::class, 'store']);
    Route::get('/pc-mantenimientos/{id}', [\App\Modules\GestionSistemas\Presentation\Controllers\PcMantenimientoController::class, 'show']);
    Route::get('/pc-mantenimientos/{id}/exportar-excel', [\App\Modules\GestionSistemas\Presentation\Controllers\PcMantenimientoController::class, 'exportarExcel']);
    Route::get('/pc-mantenimientos/{id}/exportar-pdf', [\App\Modules\GestionSistemas\Presentation\Controllers\PcMantenimientoController::class, 'exportarPdf']);
    Route::put('/pc-mantenimientos/{id}', [\App\Modules\GestionSistemas\Presentation\Controllers\PcMantenimientoController::class, 'update']);
    Route::delete('/pc-mantenimientos/{id}', [\App\Modules\GestionSistemas\Presentation\Controllers\PcMantenimientoController::class, 'destroy']);
    Route::post('/pc-mantenimientos/{id}/actualizar-firmas', [\App\Modules\GestionSistemas\Presentation\Controllers\PcMantenimientoController::class, 'actualizarFirmas']);
});

Route::middleware('auth:api')->group(function () {
    // Imagen Mensual
    Route::post('/imagen-mensual', [\App\Modules\GestionSistemas\Presentation\Controllers\ImagenMensualController::class, 'subir']);
    Route::get('/imagen-mensual', [\App\Modules\GestionSistemas\Presentation\Controllers\ImagenMensualController::class, 'descargar']);
    Route::delete('/imagen-mensual', [\App\Modules\GestionSistemas\Presentation\Controllers\ImagenMensualController::class, 'eliminar']);
});
