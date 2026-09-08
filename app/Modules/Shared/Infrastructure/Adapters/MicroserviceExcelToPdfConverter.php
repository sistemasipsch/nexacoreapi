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
                $response = Http::timeout(6)->withHeaders([
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

        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $sheet->getPageSetup()->setPrintArea("A1:{$highestCol}{$highestRow}");
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        while ($spreadsheet->getSheetCount() > 1) {
            $spreadsheet->removeSheetByIndex(1);
        }

        $tempPdfPath = tempnam(sys_get_temp_dir(), 'pdf_out_') . '.pdf';
        IOFactory::registerWriter('Pdf', \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf::class);
        $writer = IOFactory::createWriter($spreadsheet, 'Pdf');
        $writer->save($tempPdfPath);
        $spreadsheet->disconnectWorksheets();

        $content = file_get_contents($tempPdfPath);
        @unlink($tempPdfPath);

        return $content;
    }
}
