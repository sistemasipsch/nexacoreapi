<?php

namespace App\Modules\GestionCompras\Application\UseCases\Pedidos;

use App\Models\CpPedido;
use App\Exports\CpPedidoExport;
use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Exception;

class ExportarPedidoPdfUseCase extends CpPedidoExport
{
    public function __construct(
        protected ExcelToPdfConverterInterface $pdfConverter
    ) {}

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

        $this->extra = max(0, $items->count() - 12);
        if ($this->extra > 0) {
            $this->insertarFilasExtra();
        }

        $this->llenarEncabezado($pedido);
        $this->llenarItems($items);
        $this->insertarFirmas($pedido);
        $this->responsableProceso($pedido);
        $this->configurarPagina();

        $proceso     = $this->sanitize($pedido->solicitante?->nombre ?? 'SIN_PROCESO');
        $sede        = $this->sanitize($pedido->sede?->nombre ?? 'SIN_SEDE');
        $consecutivo = $this->sanitize($pedido->consecutivo ?? 'SIN_CONSECUTIVO');
        $filename    = "N.{$consecutivo} SOLICITUD DE PEDIDO {$proceso} {$sede}.pdf";

        $tempExcelPath = tempnam(sys_get_temp_dir(), 'pedido_excel_') . '.xlsx';

        // Remover otras hojas para evitar que LibreOffice genere páginas extra en el PDF
        while ($spreadsheet->getSheetCount() > 1) {
            $activeIndex = $spreadsheet->getActiveSheetIndex();
            $indexToRemove = $activeIndex === 0 ? 1 : 0;
            $spreadsheet->removeSheetByIndex($indexToRemove);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempExcelPath);
        $spreadsheet->disconnectWorksheets();

        try {
            $pdfContent = $this->pdfConverter->convert($tempExcelPath);
            @unlink($tempExcelPath);

            return new StreamedResponse(function () use ($pdfContent) {
                echo $pdfContent;
            }, 200, [
                'Content-Type'                => 'application/pdf',
                'Content-Disposition'         => 'attachment; filename="' . $filename . '"',
                'Content-Length'              => strlen($pdfContent),
                'Cache-Control'               => 'max-age=0',
                'Access-Control-Expose-Headers' => 'Content-Disposition',
            ]);
        } catch (Exception $e) {
            @unlink($tempExcelPath);
            throw $e;
        }
    }
}
