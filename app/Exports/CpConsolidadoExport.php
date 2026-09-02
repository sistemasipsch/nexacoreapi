<?php

/**
 * [NEW] CpConsolidadoExport.php 
 * Replaces legacy exportar_consolidado_pedidos.php
 */

namespace App\Exports;

use App\Models\CpPedido;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Exception;

class CpConsolidadoExport
{
    /**
     * Generate and stream the Excel file for a consolidated report.
     */
    public function generate(array $filters): StreamedResponse
    {
        $query = CpPedido::with(['solicitante', 'sede', 'tipoSolicitud', 'elaboradoPor', 'items']);

        // Apply filters (matching InformeConsolidadoPage.jsx filters)
        if (!empty($filters['fecha_desde'])) {
            $query->where('fecha_solicitud', '>=', $filters['fecha_desde']);
        }
        if (!empty($filters['fecha_hasta'])) {
            $query->where('fecha_solicitud', '<=', $filters['fecha_hasta']);
        }
        if (!empty($filters['sede_id'])) {
            $query->where('sede_id', $filters['sede_id']);
        }
        if (!empty($filters['proceso'])) {
            $query->whereHas('solicitante', function ($q) use ($filters) {
                $q->where('nombre', $filters['proceso']);
            });
        }
        if (!empty($filters['elaborado_por'])) {
            $query->where('elaborado_por', $filters['elaborado_por']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('consecutivo', 'like', "%{$search}%")
                    ->orWhere('observacion', 'like', "%{$search}%");
            });
        }

        $pedidos = $query->orderBy('fecha_solicitud', 'asc')->get();

        $templatePath = storage_path('app/templates/plantilla_consolidadoPedidos.xlsx');

        if (!file_exists($templatePath)) {
            throw new Exception('No se encontró la plantilla de consolidado de pedidos.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Asegurar encabezado de la columna ENTREGADO
        $sheet->setCellValue('L2', 'ENTREGADO');
        $sheet->duplicateStyle($sheet->getStyle('K2'), 'L2');
        $sheet->getColumnDimension('L')->setAutoSize(true);

        $startRow = 3;
        foreach ($pedidos as $i => $pedido) {
            $row = $startRow + $i;

            // Format items description: Name (Qty), Name (Qty)...
            $descripcion = $pedido->items->map(function ($item) {
                if ($item->fecha_entregado) {
                    $fecha = \Carbon\Carbon::parse($item->fecha_entregado, 'UTC')
                        ->setTimezone('America/Bogota')
                        ->format('d/m/Y g:i A');
                    $fechaEntregado = ' - Entregado: ' . $fecha;
                } else {
                    $fechaEntregado = '';
                }
                return "{$item->nombre} ({$item->cantidad}){$fechaEntregado}";
            })->implode(', ');

            // Obtener fecha final de entrega del pedido
            $fechasEntregado = $pedido->items
                ->where('comprado', 1)
                ->pluck('fecha_entregado')
                ->filter()
                ->map(function ($f) {
                    return \Carbon\Carbon::parse($f, 'UTC')->setTimezone('America/Bogota');
                });

            $fechaFinalEntregado = '';
            if ($fechasEntregado->isNotEmpty()) {
                $fechaFinalEntregado = $fechasEntregado->max()->format('d/m/Y g:i A');
            } elseif ($pedido->items->where('comprado', 1)->count() > 0) {
                if ($pedido->fecha_compra) {
                    $fechaFinalEntregado = \Carbon\Carbon::parse($pedido->fecha_compra, 'UTC')->setTimezone('America/Bogota')->format('d/m/Y g:i A');
                } elseif ($pedido->fecha_solicitud) {
                    $fechaFinalEntregado = \Carbon\Carbon::parse($pedido->fecha_solicitud, 'UTC')->setTimezone('America/Bogota')->format('d/m/Y g:i A');
                }
            }

            $sheet->setCellValue("A{$row}", $pedido->fecha_solicitud);
            $sheet->setCellValue("B{$row}", $pedido->solicitante?->nombre);
            $sheet->setCellValue("C{$row}", $pedido->sede?->nombre);
            $sheet->setCellValue("D{$row}", $pedido->consecutivo);
            $sheet->setCellValue("E{$row}", $descripcion);
            $sheet->setCellValue("F{$row}", $pedido->observacion);
            $sheet->setCellValue("G{$row}", $pedido->tipoSolicitud?->nombre);

            // Colorear según el tipo de solicitud
            $tipoNombre = $pedido->tipoSolicitud?->nombre;
            if ($tipoNombre === 'Prioritaria') {
                $sheet->getStyle("G{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('92D050'); // Verde
            } elseif ($tipoNombre === 'Recurrente') {
                $sheet->getStyle("G{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFC000'); // Naranja
            }
            $sheet->setCellValue("H{$row}", $pedido->estado_compras);
            $sheet->setCellValue("I{$row}", $pedido->fecha_compra); // FECHA_RESPUESTA
            $sheet->setCellValue("J{$row}", $pedido->fecha_gerencia); // FECHA_RESPUESTA_SOLICITANTE
            $sheet->setCellValue("K{$row}", $pedido->observaciones_pedidos);
            $sheet->setCellValue("L{$row}", $fechaFinalEntregado);
        }

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="CONSOLIDADO PEDIDOS.xlsx"',
            'Cache-Control' => 'max-age=0',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);
    }
}
