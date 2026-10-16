<?php

namespace App\Modules\GestionCompras\Application\UseCases\Pedidos;

use App\Models\CpPedido;
use App\Exports\CpPedidoExport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Exception;

class ExportarPedidoExcelUseCase extends CpPedidoExport
{
    public function execute(int $pedidoId): StreamedResponse
    {
        $pedido = CpPedido::with([
            'solicitante',
            'tipoSolicitud',
            'sede',
            'elaboradoPor.rol',
            'procesoCompra.rol',
            'responsableAprobacion.rol',
            'items.producto',
        ])->findOrFail($pedidoId);

        $templatePath = storage_path('app/templates/plantilla_pedidos.xlsx');

        if (!file_exists($templatePath)) {
            throw new Exception('No se encontró la plantilla de pedidos.');
        }

        $spreadsheet = IOFactory::load($templatePath);
        $this->sheet = $spreadsheet->getActiveSheet();

        $items = $pedido->items;

        // Dynamic row adjustment for > 12 items
        $this->extra = max(0, $items->count() - 12);
        if ($this->extra > 0) {
            $this->insertarFilasExtra();
        }

        // Fill data
        $this->llenarEncabezado($pedido);
        $this->llenarItems($items);
        $this->insertarFirmas($pedido);
        $this->responsableProceso($pedido);
        $this->configurarPagina();

        // Build filename
        $proceso = $this->sanitize($pedido->solicitante?->nombre ?? 'SIN_PROCESO');
        $sede = $this->sanitize($pedido->sede?->nombre ?? 'SIN_SEDE');
        $consecutivo = $this->sanitize($pedido->consecutivo ?? 'SIN_CONSECUTIVO');
        $filename = "N.{$consecutivo} SOLICITUD DE PEDIDO {$proceso} {$sede}.xlsx";

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);
    }
}
