<?php

/**
 * CpConsolidadoExport.php 
 * Generates Excel and PDF for consolidated orders report.
 * Uses a clean single-row-per-order layout with vertical bulleted items in column E.
 */

namespace App\Exports;

use App\Models\CpPedido;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Mpdf\Mpdf;
use Carbon\Carbon;
use Exception;

class CpConsolidadoExport
{
    /**
     * Formatea cualquier fecha/hora tal como está guardada en la BD (hora local).
     */
    private function formatDateTime($value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                $raw = $value->format('Y-m-d H:i:s');
            } else {
                $raw = trim((string) $value);
            }

            if (empty($raw)) {
                return '';
            }

            $c = Carbon::parse($raw);

            if ($c->format('H:i:s') === '00:00:00') {
                return $c->format('d/m/Y');
            }

            return $c->format('d/m/Y g:i A');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    /**
     * Obtiene los pedidos filtrados según los parámetros de búsqueda.
     */
    private function getFilteredQuery(array $filters)
    {
        $query = CpPedido::with(['solicitante', 'sede', 'tipoSolicitud', 'elaboradoPor', 'items']);

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

        return $query->orderBy('fecha_solicitud', 'asc');
    }

    /**
     * Construye la hoja de cálculo Excel con diseño tabular profesional:
     * - Cada ítem del pedido ocupa su propia fila/celda individual en la columna E.
     * - Las celdas con datos generales del pedido (columnas A-D y F-L) se combinan
     *   verticalmente para abarcar todos los ítems del pedido, centradas verticalmente.
     */
    public function buildSpreadsheet(array $filters): Spreadsheet
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        $pedidos = $this->getFilteredQuery($filters)->get();

        $templatePath = storage_path('app/templates/plantilla_consolidadoPedidos.xlsx');
        if (!file_exists($templatePath)) {
            throw new Exception('No se encontró la plantilla de consolidado de pedidos.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Extender el encabezado del título en fila 1 hasta columna L
        $sheet->duplicateStyle($sheet->getStyle('K1'), 'L1');
        $sheet->unmergeCells('A1:K1');
        $sheet->mergeCells('A1:L1');

        // Encabezado de la columna ENTREGADO
        $sheet->setCellValue('L2', 'ENTREGADO');
        $sheet->duplicateStyle($sheet->getStyle('K2'), 'L2');

        // Ajustar anchos de columnas para óptima legibilidad
        $sheet->getColumnDimension('A')->setWidth(21);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(48);
        $sheet->getColumnDimension('F')->setWidth(28);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(16);
        $sheet->getColumnDimension('I')->setWidth(21);
        $sheet->getColumnDimension('J')->setWidth(21);
        $sheet->getColumnDimension('K')->setWidth(26);
        $sheet->getColumnDimension('L')->setWidth(22);

        $currentRow = 3;
        foreach ($pedidos as $pedido) {
            $items = $pedido->items;
            $totalItems = $items->count();
            $filasPedido = max(1, $totalItems);
            $startRow = $currentRow;
            $endRow = $startRow + $filasPedido - 1;

            $itemsComprados = $items->where('comprado', 1)->count();
            $isEntregado = ($totalItems > 0 && $itemsComprados === $totalItems);

            $fechasEntregado = $items->where('comprado', 1)->pluck('fecha_entregado')->filter();
            $fechaFinalEntregado = '';
            if ($isEntregado) {
                if ($fechasEntregado->isNotEmpty()) {
                    $fechaFinalEntregado = $this->formatDateTime($fechasEntregado->max());
                } elseif ($pedido->fecha_compra) {
                    $fechaFinalEntregado = $this->formatDateTime($pedido->fecha_compra);
                } elseif ($pedido->fecha_solicitud) {
                    $fechaFinalEntregado = $this->formatDateTime($pedido->fecha_solicitud);
                }
            }

            $contenidoEntrega = $isEntregado ? "Entregado" . (!empty($fechaFinalEntregado) ? "\n" . $fechaFinalEntregado : "") : "Pendiente";

            // Asignar valores generales del pedido en startRow
            $sheet->setCellValue("A{$startRow}", $this->formatDateTime($pedido->fecha_solicitud));
            $sheet->setCellValue("B{$startRow}", $pedido->solicitante?->nombre);
            $sheet->setCellValue("C{$startRow}", $pedido->sede?->nombre);
            $sheet->setCellValue("D{$startRow}", $pedido->consecutivo);
            $sheet->setCellValue("F{$startRow}", $pedido->observacion);
            $sheet->setCellValue("G{$startRow}", $pedido->tipoSolicitud?->nombre);
            $sheet->setCellValue("H{$startRow}", $pedido->estado_compras);
            $sheet->setCellValue("I{$startRow}", $this->formatDateTime($pedido->fecha_compra));
            $sheet->setCellValue("J{$startRow}", $this->formatDateTime($pedido->fecha_gerencia));
            $sheet->setCellValue("K{$startRow}", $pedido->observaciones_pedidos);
            $sheet->setCellValue("L{$startRow}", $contenidoEntrega);

            // Asignar cada ítem en su propia fila individual en la columna E
            if ($totalItems > 0) {
                foreach ($items->values() as $idx => $item) {
                    $itemRow = $startRow + $idx;
                    $fechaEnt = $item->fecha_entregado ? ' - Entregado: ' . $this->formatDateTime($item->fecha_entregado) : '';
                    $sheet->setCellValue("E{$itemRow}", "• {$item->nombre} ({$item->cantidad}){$fechaEnt}");
                }
            } else {
                $sheet->setCellValue("E{$startRow}", "—");
            }

            // Combinar verticalmente las celdas de datos del pedido si tiene más de 1 ítem
            if ($filasPedido > 1) {
                foreach (['A', 'B', 'C', 'D', 'F', 'G', 'H', 'I', 'J', 'K', 'L'] as $col) {
                    $sheet->mergeCells("{$col}{$startRow}:{$col}{$endRow}");
                }
            }

            // Color para tipo de compra (G)
            $tipoNombre = $pedido->tipoSolicitud?->nombre;
            if ($tipoNombre === 'Prioritaria') {
                $sheet->getStyle("G{$startRow}:G{$endRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('92D050');
            } elseif ($tipoNombre === 'Recurrente') {
                $sheet->getStyle("G{$startRow}:G{$endRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC000');
            }

            // Color y formato para columna L
            $rangeL = "L{$startRow}:L{$endRow}";
            if ($isEntregado) {
                $sheet->getStyle($rangeL)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E2EFDA');
                $sheet->getStyle($rangeL)->getFont()->getColor()->setARGB('276A3C');
                $sheet->getStyle($rangeL)->getFont()->setBold(true);
            } else {
                $sheet->getStyle($rangeL)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2CC');
                $sheet->getStyle($rangeL)->getFont()->getColor()->setARGB('8A6D3B');
                $sheet->getStyle($rangeL)->getFont()->setBold(true);
            }

            $currentRow = $endRow + 1;
        }

        $highestRow = max(3, $currentRow - 1);

        // Estilización en lote de alto rendimiento
        $sheet->getStyle("A3:L{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A3:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D3:D{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E3:F{$highestRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("G3:J{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("K3:L{$highestRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("L3:L{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Bordes uniformes en toda la tabla (cada celda de ítem queda separada con sus líneas)
        $sheet->getStyle("A3:L{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('D9D9D9');

        // Recortar filas vacías sobrantes de la plantilla
        if ($sheet->getHighestRow() > $highestRow) {
            $sheet->removeRow($highestRow + 1, $sheet->getHighestRow() - $highestRow);
        }

        // Configuración de página horizontal
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageSetup()->setPrintArea("A1:L{$highestRow}");

        return $spreadsheet;
    }

    /**
     * Genera y transmite el archivo Excel (.xlsx).
     */
    public function generate(array $filters): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet($filters);

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

    /**
     * Genera y transmite el archivo PDF (.pdf) directamente con mPDF optimizado:
     * - Cada ítem del pedido ocupa una fila limpia con sus propios bordes.
     * - Las celdas de datos del pedido (rowspan) se mantienen unificadas y centradas verticalmente.
     * - Utiliza una tabla continua con encabezados repetitivos (thead) y control de saltos
     *   de página para eliminar por completo el solapamiento de textos o remontes al paginar.
     */
    public function generatePdf(array $filters): StreamedResponse
    {
        ini_set('memory_limit', '1024M');
        ini_set('pcre.backtrack_limit', '50000000');
        set_time_limit(300);

        $pedidos = $this->getFilteredQuery($filters)->get();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'Letter-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'tempDir' => sys_get_temp_dir() . '/mpdf'
        ]);

        $mpdf->SetTitle('CONSOLIDADO PEDIDOS');

        $css = '
        <style>
            body { font-family: sans-serif; font-size: 7.2pt; }
            table { width: 100%; border-collapse: collapse; table-layout: fixed; }
            thead { display: table-header-group; }
            th { background-color: #2F5597; color: #ffffff; font-weight: bold; font-size: 6.8pt; text-align: center; padding: 5px 3px; border: 0.5px solid #000; }
            td { font-size: 6.8pt; padding: 3px 4px; border: 0.5px solid #D9D9D9; vertical-align: middle; word-wrap: break-word; }
            .center { text-align: center; }
            .bg-green { background-color: #E2EFDA; color: #276A3C; font-weight: bold; }
            .bg-yellow { background-color: #FFF2CC; color: #8A6D3B; font-weight: bold; }
            .bg-prio { background-color: #92D050; text-align: center; font-weight: bold; }
            .bg-recu { background-color: #FFC000; text-align: center; font-weight: bold; }
            .title-box { text-align: center; font-size: 11pt; font-weight: bold; margin-bottom: 8px; color: #1F497D; }
            .item-bullet { color: #1F497D; font-weight: bold; margin-right: 3px; }
            .item-cell { text-align: left; vertical-align: middle; padding: 3px 4px; border: 0.5px solid #D9D9D9; }
            .order-group { page-break-inside: avoid; }
        </style>
        ';

        $mpdf->WriteHTML($css);
        $mpdf->WriteHTML('<div class="title-box">REGISTRO CONSOLIDADO DE SOLICITUDES DE PEDIDOS POR PROCESOS</div>');

        $html = '<table>
            <thead>
                <tr>
                    <th style="width: 8.5%;">FECHA SOLICITUD</th>
                    <th style="width: 11%;">PROCESO</th>
                    <th style="width: 7.5%;">SEDE</th>
                    <th style="width: 7%; white-space: nowrap;">CONSECUTIVO</th>
                    <th style="width: 27%;">DESCRIPCION DE LA SOLICITUD (ITEMS)</th>
                    <th style="width: 12%;">OBS. SOLICITUD</th>
                    <th style="width: 6.5%;">TIPO</th>
                    <th style="width: 6.5%;">ESTADO</th>
                    <th style="width: 7%;">FECHA COMPRA</th>
                    <th style="width: 7%;">ENTREGADO</th>
                </tr>
            </thead>';

        foreach ($pedidos as $pedido) {
            $items = $pedido->items;
            $totalItems = $items->count();
            $filasPedido = max(1, $totalItems);

            $itemsComprados = $items->where('comprado', 1)->count();
            $isEntregado = ($totalItems > 0 && $itemsComprados === $totalItems);
            $entregaClass = $isEntregado ? 'bg-green' : 'bg-yellow';

            $fechasEntregado = $items->where('comprado', 1)->pluck('fecha_entregado')->filter();
            $fechaFinalEntregado = '';
            if ($isEntregado) {
                if ($fechasEntregado->isNotEmpty()) {
                    $fechaFinalEntregado = $this->formatDateTime($fechasEntregado->max());
                } elseif ($pedido->fecha_compra) {
                    $fechaFinalEntregado = $this->formatDateTime($pedido->fecha_compra);
                } elseif ($pedido->fecha_solicitud) {
                    $fechaFinalEntregado = $this->formatDateTime($pedido->fecha_solicitud);
                }
            }

            $entregaHtml = $isEntregado 
                ? 'Entregado' . (!empty($fechaFinalEntregado) ? '<br><span style="font-size:5.8pt; font-weight:normal;">' . $fechaFinalEntregado . '</span>' : '')
                : 'Pendiente';

            $tipoClass = '';
            $tipoNombre = $pedido->tipoSolicitud?->nombre ?? '';
            if ($tipoNombre === 'Prioritaria') $tipoClass = ' class="bg-prio"';
            elseif ($tipoNombre === 'Recurrente') $tipoClass = ' class="bg-recu"';

            $fechaSol = $this->formatDateTime($pedido->fecha_solicitud);
            $proceso = htmlspecialchars($pedido->solicitante?->nombre ?? '');
            $sede = htmlspecialchars($pedido->sede?->nombre ?? '');
            $consecutivo = htmlspecialchars($pedido->consecutivo ?? '');
            $obs = htmlspecialchars($pedido->observacion ?? '');
            $estado = htmlspecialchars($pedido->estado_compras ?? '');
            $fechaCompra = $this->formatDateTime($pedido->fecha_compra);

            // Formatear cada ítem con su viñeta estilizada y fecha de entrega si la tiene
            $formattedItems = [];
            if ($totalItems > 0) {
                foreach ($items->values() as $item) {
                    $ent = $item->fecha_entregado ? ' <span style="color:#555; font-size:5.8pt;">(Entregado: ' . $this->formatDateTime($item->fecha_entregado) . ')</span>' : '';
                    $formattedItems[] = '<span class="item-bullet">&bull;</span> ' . htmlspecialchars("{$item->nombre} ({$item->cantidad})") . $ent;
                }
            } else {
                $formattedItems[] = '<span style="color:#999;">—</span>';
            }

            // Los pedidos con hasta 25 ítems se mantienen en un bloque indivisible para evitar saltos de página defectuosos
            $tbodyClass = ($filasPedido <= 25) ? ' class="order-group"' : '';
            $html .= "<tbody{$tbodyClass}>";

            // Fila 1 del pedido: incluye los datos generales combinados (rowspan) y el primer ítem
            $html .= "<tr>";
            $html .= "<td rowspan=\"{$filasPedido}\" class=\"center\">{$fechaSol}</td>";
            $html .= "<td rowspan=\"{$filasPedido}\">{$proceso}</td>";
            $html .= "<td rowspan=\"{$filasPedido}\" class=\"center\">{$sede}</td>";
            $html .= "<td rowspan=\"{$filasPedido}\" class=\"center\" style=\"white-space: nowrap;\"><strong>{$consecutivo}</strong></td>";
            $html .= "<td class=\"item-cell\">{$formattedItems[0]}</td>";
            $html .= "<td rowspan=\"{$filasPedido}\">{$obs}</td>";
            $html .= "<td rowspan=\"{$filasPedido}\"{$tipoClass}>{$tipoNombre}</td>";
            $html .= "<td rowspan=\"{$filasPedido}\" class=\"center\">{$estado}</td>";
            $html .= "<td rowspan=\"{$filasPedido}\" class=\"center\">{$fechaCompra}</td>";
            $html .= "<td rowspan=\"{$filasPedido}\" class=\"center {$entregaClass}\">{$entregaHtml}</td>";
            $html .= "</tr>";

            // Filas subsiguientes: una por cada ítem adicional
            for ($j = 1; $j < $filasPedido; $j++) {
                $html .= "<tr>";
                $html .= "<td class=\"item-cell\">{$formattedItems[$j]}</td>";
                $html .= "</tr>";
            }

            $html .= "</tbody>";
        }

        $html .= "</table>";
        $mpdf->WriteHTML($html);

        $pdfContent = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

        return new StreamedResponse(function () use ($pdfContent) {
            echo $pdfContent;
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="CONSOLIDADO PEDIDOS.pdf"',
            'Content-Length' => strlen($pdfContent),
            'Cache-Control' => 'max-age=0',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);
    }
}
