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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;
use Exception;

class CpConsolidadoExport
{
    /**
     * Formatea cualquier fecha/hora a la zona horaria de Colombia (America/Bogota)
     * mostrando fecha exacta y hora exacta con formato 12h (ej: 25/08/2026 2:52 PM).
     */
    private function formatDateTime($value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value)->setTimezone('America/Bogota')->format('d/m/Y g:i A');
            }

            return Carbon::parse($value, 'UTC')->setTimezone('America/Bogota')->format('d/m/Y g:i A');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

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

        // Extender el encabezado del título en fila 1 hasta columna M
        $sheet->duplicateStyle($sheet->getStyle('K1'), 'L1');
        $sheet->duplicateStyle($sheet->getStyle('K1'), 'M1');
        $sheet->unmergeCells('A1:K1');
        $sheet->mergeCells('A1:M1');

        // Encabezados de columnas de entrega
        $sheet->setCellValue('L2', 'ESTADO ENTREGA');
        $sheet->duplicateStyle($sheet->getStyle('K2'), 'L2');
        $sheet->setCellValue('M2', 'FECHA DE ENTREGA');
        $sheet->duplicateStyle($sheet->getStyle('K2'), 'M2');

        // Ajustar anchos de columnas para asegurar legibilidad completa de fechas y horas
        $sheet->getColumnDimension('A')->setWidth(23);
        $sheet->getColumnDimension('I')->setWidth(23);
        $sheet->getColumnDimension('J')->setWidth(26);
        $sheet->getColumnDimension('L')->setWidth(20);
        $sheet->getColumnDimension('M')->setWidth(23);

        $startRow = 3;
        foreach ($pedidos as $i => $pedido) {
            $row = $startRow + $i;

            // Format items description: Name (Qty) - Entregado: [Fecha y hora]
            $descripcion = $pedido->items->map(function ($item) {
                if ($item->fecha_entregado) {
                    $fecha = $this->formatDateTime($item->fecha_entregado);
                    $fechaEntregado = ' - Entregado: ' . $fecha;
                } else {
                    $fechaEntregado = '';
                }
                return "{$item->nombre} ({$item->cantidad}){$fechaEntregado}";
            })->implode(', ');

            // Determinar si el pedido está 100% entregado
            $totalItems = $pedido->items->count();
            $itemsComprados = $pedido->items->where('comprado', 1)->count();
            $isEntregado = ($totalItems > 0 && $itemsComprados === $totalItems);

            // Obtener fecha final de entrega del pedido si está entregado
            $fechasEntregado = $pedido->items
                ->where('comprado', 1)
                ->pluck('fecha_entregado')
                ->filter()
                ->map(function ($f) {
                    return Carbon::parse($f, 'UTC')->setTimezone('America/Bogota');
                });

            $fechaFinalEntregado = '';
            if ($isEntregado) {
                if ($fechasEntregado->isNotEmpty()) {
                    $fechaFinalEntregado = $fechasEntregado->max()->format('d/m/Y g:i A');
                } elseif ($pedido->fecha_compra) {
                    $fechaFinalEntregado = $this->formatDateTime($pedido->fecha_compra);
                } elseif ($pedido->fecha_solicitud) {
                    $fechaFinalEntregado = $this->formatDateTime($pedido->fecha_solicitud);
                }
            }

            $estadoEntrega = $isEntregado ? 'Entregado' : 'Pendiente';

            // Asignar celdas con fechas y horas exactas formateadas en zona horaria Colombia
            $sheet->setCellValue("A{$row}", $this->formatDateTime($pedido->fecha_solicitud));
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
            $sheet->setCellValue("I{$row}", $this->formatDateTime($pedido->fecha_compra));
            $sheet->setCellValue("J{$row}", $this->formatDateTime($pedido->fecha_gerencia));
            $sheet->setCellValue("K{$row}", $pedido->observaciones_pedidos);

            // Columna L (Estado Entrega) y Columna M (Fecha Entrega)
            $sheet->setCellValue("L{$row}", $estadoEntrega);
            $sheet->duplicateStyle($sheet->getStyle("K{$row}"), "L{$row}");
            $sheet->setCellValue("M{$row}", $fechaFinalEntregado);
            $sheet->duplicateStyle($sheet->getStyle("K{$row}"), "M{$row}");

            // Resaltar estado de entrega visualmente
            if ($isEntregado) {
                $sheet->getStyle("L{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('E2EFDA'); // Verde suave
                $sheet->getStyle("L{$row}")->getFont()->getColor()->setARGB('276A3C');
                $sheet->getStyle("L{$row}")->getFont()->setBold(true);
            } else {
                $sheet->getStyle("L{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF2CC'); // Amarillo suave
                $sheet->getStyle("L{$row}")->getFont()->getColor()->setARGB('8A6D3B');
                $sheet->getStyle("L{$row}")->getFont()->setBold(true);
            }

            // Centrar celdas de fechas y estado
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("M{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
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
