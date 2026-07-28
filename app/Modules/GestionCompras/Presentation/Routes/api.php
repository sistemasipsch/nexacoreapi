<?php

use Illuminate\Support\Facades\Route;
use App\Modules\GestionCompras\Presentation\Controllers\InventarioSearchController;

Route::prefix('gestion-compras')->middleware('auth:api')->group(function () {
    Route::get('/inventario/buscar', [InventarioSearchController::class, 'search']);

    // Gestion Compras Generales
    Route::apiResource('cp-centro-costos', \App\Modules\GestionCompras\Presentation\Controllers\CpCentroCostoController::class);
    Route::apiResource('cp-dependencias', \App\Modules\GestionCompras\Presentation\Controllers\CpDependenciaController::class);
    Route::get('cp-productos/todos', [\App\Modules\GestionCompras\Presentation\Controllers\CpProductoController::class, 'all']);
    Route::post('cp-productos/sincronizar', [\App\Modules\GestionCompras\Presentation\Controllers\CpProductoController::class, 'sincronizar']);
    Route::apiResource('cp-productos', \App\Modules\GestionCompras\Presentation\Controllers\CpProductoController::class);
    Route::get('cp-productos-servicios/buscar', [\App\Modules\GestionCompras\Presentation\Controllers\CpProductoServicioController::class, 'buscar']);
    Route::get('cp-productos-servicios/buscar-externo', [\App\Modules\GestionCompras\Presentation\Controllers\CpProductoServicioController::class, 'buscarExterno']);
    Route::get('cp-productos-servicios/todos', [\App\Modules\GestionCompras\Presentation\Controllers\CpProductoServicioController::class, 'all']);
    Route::apiResource('cp-productos-servicios', \App\Modules\GestionCompras\Presentation\Controllers\CpProductoServicioController::class);
    Route::apiResource('cp-proveedores', \App\Modules\GestionCompras\Presentation\Controllers\CpProveedorController::class);
    Route::apiResource('cp-tipos-solicitud', \App\Modules\GestionCompras\Presentation\Controllers\CpTipoSolicitudController::class);

    // Entregas de Activos Fijos
    Route::get('cp-entrega-activos-fijos/coordinadores', [\App\Modules\GestionCompras\Presentation\Controllers\CpEntregaActivosFijosController::class, 'coordinadores']);
    Route::get('cp-entrega-activos-fijos/coordinador/{id}', [\App\Modules\GestionCompras\Presentation\Controllers\CpEntregaActivosFijosController::class, 'porCoordinador']);
    Route::post('cp-entrega-activos-fijos/transferir-todo', [\App\Modules\GestionCompras\Presentation\Controllers\CpEntregaActivosFijosController::class, 'transferirTodo']);
    Route::get('cp-entrega-activos-fijos/{id}/exportar-excel', [\App\Modules\GestionCompras\Presentation\Controllers\CpEntregaActivosFijosController::class, 'exportExcel']);
    Route::post('cp-entrega-activos-fijos/{id}/transferir', [\App\Modules\GestionCompras\Presentation\Controllers\CpEntregaActivosFijosController::class, 'transferir']);
    Route::apiResource('cp-entrega-activos-fijos', \App\Modules\GestionCompras\Presentation\Controllers\CpEntregaActivosFijosController::class);

    // Pedidos
    Route::post('cp-pedidos/exportar-consolidado', [\App\Modules\GestionCompras\Presentation\Controllers\CpPedidoController::class, 'exportConsolidadoExcel']);
    Route::apiResource('cp-pedidos', \App\Modules\GestionCompras\Presentation\Controllers\CpPedidoController::class);
    Route::post('cp-pedidos/{id}', [\App\Modules\GestionCompras\Presentation\Controllers\CpPedidoController::class, 'update']);
    Route::post('cp-pedidos/{id}/aprobar-compras', [\App\Modules\GestionCompras\Presentation\Controllers\CpPedidoController::class, 'aprobarCompras']);
    Route::post('cp-pedidos/{id}/rechazar-compras', [\App\Modules\GestionCompras\Presentation\Controllers\CpPedidoController::class, 'rechazarCompras']);
    Route::post('cp-pedidos/{id}/aprobar-gerencia', [\App\Modules\GestionCompras\Presentation\Controllers\CpPedidoController::class, 'aprobarGerencia']);
    Route::post('cp-pedidos/{id}/rechazar-gerencia', [\App\Modules\GestionCompras\Presentation\Controllers\CpPedidoController::class, 'rechazarGerencia']);
    Route::post('cp-pedidos/{id}/update-items', [\App\Modules\GestionCompras\Presentation\Controllers\CpPedidoController::class, 'updateItems']);
    Route::post('cp-pedidos/{id}/tracking', [\App\Modules\GestionCompras\Presentation\Controllers\CpPedidoController::class, 'updateTracking']);
    Route::get('cp-pedidos/{id}/exportar-excel', [\App\Modules\GestionCompras\Presentation\Controllers\CpPedidoController::class, 'exportExcel']);
    Route::get('cp-pedidos/{id}/exportar-pdf', [\App\Modules\GestionCompras\Presentation\Controllers\CpPedidoController::class, 'exportPdf']);
    Route::get('cp-pedidos/{id}/tiempos', [\App\Modules\GestionCompras\Presentation\Controllers\CpPedidoController::class, 'calcularTiempoEntregaPedido']);
    Route::get('cp-pedidos/{id}/estadisticas', [\App\Modules\GestionCompras\Presentation\Controllers\CpPedidoController::class, 'obtenerEstadisticas']);

    // Pedidos Programados
    Route::post('pedidos-programados', [\App\Modules\GestionCompras\Presentation\Controllers\CpPedidoProgramadoController::class, 'store']);
    Route::get('pedidos-programados', [\App\Modules\GestionCompras\Presentation\Controllers\CpPedidoProgramadoController::class, 'index']);
    Route::get('pedidos-programados/{id}', [\App\Modules\GestionCompras\Presentation\Controllers\CpPedidoProgramadoController::class, 'show']);
    Route::post('pedidos-programados/{id}', [\App\Modules\GestionCompras\Presentation\Controllers\CpPedidoProgramadoController::class, 'update']); // usamos post para simular put y enviar archivos
    Route::delete('pedidos-programados/{id}', [\App\Modules\GestionCompras\Presentation\Controllers\CpPedidoProgramadoController::class, 'destroy']);
});
