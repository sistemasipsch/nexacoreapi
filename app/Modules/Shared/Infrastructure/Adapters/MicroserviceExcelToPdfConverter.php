<?php

namespace App\Modules\Shared\Infrastructure\Adapters;

use App\Modules\Shared\Domain\Contracts\ExcelToPdfConverterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Exception;

class MicroserviceExcelToPdfConverter implements ExcelToPdfConverterInterface
{
    public function convert(string $excelFilePath): string
    {
        $url = config('services.convertidor.url');
        $apiKey = config('services.convertidor.api_key');

        if (!file_exists($excelFilePath)) {
            throw new Exception("El archivo Excel temporal no existe.");
        }

        if (!empty($url)) {
            try {
                $endpoint = rtrim($url, '/') . '/api/convertir/excel-a-pdf';
                $response = Http::timeout(12)->withHeaders([
                    'x-api-key' => $apiKey,
                ])->attach(
                    'documento', file_get_contents($excelFilePath), basename($excelFilePath)
                )->post($endpoint);

                if ($response->successful()) {
                    return $response->body();
                }

                $error = $response->json('error') ?: $response->body() ?: 'Error desconocido';
                Log::warning("Microservicio convertidor retornó error: {$error}. Usando convertidor local mPDF.");
            } catch (\Throwable $e) {
                Log::warning("No se pudo conectar al microservicio convertidor ({$e->getMessage()}). Usando convertidor local mPDF.");
            }
        }

        return $this->convertWithLocalMpdf($excelFilePath);
    }

    private function convertWithLocalMpdf(string $excelFilePath): string
    {
        $spreadsheet = IOFactory::load($excelFilePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Respetar el área de impresión si ya está definida en el archivo (ej. B2:AL17 o A2:K42)
        if (!$sheet->getPageSetup()->getPrintArea()) {
            $sheet->getPageSetup()->setPrintArea('B2:AL17');
        }

        // Respetar orientación si ya está definida en la plantilla o exportador
        $currentOrientation = $sheet->getPageSetup()->getOrientation();
        if (!$currentOrientation || $currentOrientation === \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_DEFAULT) {
            $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        }

        if (!$sheet->getPageSetup()->getPaperSize()) {
            $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER);
        }

        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setHorizontalCentered(true);

        // Si los márgenes no fueron configurados específicamente y es Landscape, aplicar márgenes de contingencia
        if ($sheet->getPageMargins()->getLeft() >= 0.7 && $sheet->getPageSetup()->getOrientation() === \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE) {
            $sheet->getPageMargins()->setTop(0.6);
            $sheet->getPageMargins()->setBottom(0.6);
            $sheet->getPageMargins()->setLeft(0.7);
            $sheet->getPageMargins()->setRight(0.7);
        }

        while ($spreadsheet->getSheetCount() > 1) {
            $activeIndex = $spreadsheet->getActiveSheetIndex();
            $indexToRemove = $activeIndex === 0 ? 1 : 0;
            $spreadsheet->removeSheetByIndex($indexToRemove);
        }

        $tempPdfPath = tempnam(sys_get_temp_dir(), 'pdf_out_') . '.pdf';
        IOFactory::registerWriter('Pdf', \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf::class);
        $writer = IOFactory::createWriter($spreadsheet, 'Pdf');

        // Suprimir advertencias menores de PHP 8.2 en tablas complejas dentro de mPDF
        $oldHandler = set_error_handler(function ($errno, $errstr) {
            if ($errno === E_WARNING || $errno === E_NOTICE || $errno === E_DEPRECATED) {
                return true;
            }
            return false;
        });

        try {
            $writer->save($tempPdfPath);
        } finally {
            if ($oldHandler) {
                set_error_handler($oldHandler);
            } else {
                restore_error_handler();
            }
        }

        $spreadsheet->disconnectWorksheets();

        $content = file_get_contents($tempPdfPath);
        @unlink($tempPdfPath);

        return $content;
    }
}
