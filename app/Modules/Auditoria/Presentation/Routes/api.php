<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Auditoria\Presentation\Controllers\ActividadController;
use App\Modules\Auditoria\Presentation\Controllers\PedidoProgramadoAuditoriaController;

Route::middleware(['auth:api'])->prefix('auditoria')->group(function () {
    // Actividades y logs del sistema
    Route::get('actividades', [ActividadController::class, 'index']);
    Route::get('actividades/estadisticas', [ActividadController::class, 'estadisticas']);
    Route::get('actividades/{id}', [ActividadController::class, 'show'])->where('id', '[0-9]+');

    // Pedidos programados
    Route::get('pedidos-programados', [PedidoProgramadoAuditoriaController::class, 'index']);
    Route::get('pedidos-programados/{id}', [PedidoProgramadoAuditoriaController::class, 'show'])->where('id', '[0-9]+');
});
