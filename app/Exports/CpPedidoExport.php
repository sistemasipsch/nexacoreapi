<?php

namespace App\Exports;

use App\Models\CpPedido;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Illuminate\Support\Facades\Storage;
use DateTime;
use Exception;

class CpPedidoExport
{
    protected $sheet;
    protected $extra = 0;

    // ─── Encabezado ───────────────────────────────────────────
    protected function llenarEncabezado(CpPedido $pedido): void
    {
        $sheet = $this->sheet;

        // Fecha
        if ($pedido->fecha_solicitud) {
            $date = ExcelDate::PHPToExcel(new DateTime($pedido->fecha_solicitud));
            $sheet->setCellValue('E6', $date);
            $sheet->getStyle('E6')
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
        }

        // Datos generales
        $sheet->setCellValue('E7', $pedido->solicitante?->nombre);
        $sheet->setCellValue('I6', $pedido->consecutivo);
        $sheet->setCellValue('I7', $pedido->sede?->nombre);

        // Tipo de solicitud
        $tipoNombre = $pedido->tipoSolicitud?->nombre;
        if ($tipoNombre === 'Prioritaria') {
            $sheet->setCellValue('J9', 'X');
        } elseif ($tipoNombre === 'Recurrente') {
            $sheet->setCellValue('G9', 'X');
        }

        // Observaciones
        $obsRow = 26 + $this->extra;
        $obsCell = "B{$obsRow}";

        $sheet->getStyle($obsCell)
            ->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);

        $textoObs = $pedido->observacion ?? '';

        // Observaciones registradas por Compras / Gerencia
        $extrasObs = [
            'Obs. Compras' => $pedido->observaciones_pedidos,
            'Aprobación Compras' => $pedido->motivo_aprobacion_compras,
            'Rechazo Compras' => $pedido->motivo_rechazado_compras,
            'Obs. Gerencia' => $pedido->observacion_gerencia ?: $pedido->motivo_aprobacion_gerencia,
        ];
        foreach ($extrasObs as $label => $valor) {
            if (!empty(trim((string) $valor))) {
                $textoObs .= ($textoObs !== '' ? "\n" : '') . $label . ': ' . trim($valor);
            }
        }
        $currentValue = $sheet->getCell($obsCell)->getValue();
        if (!empty($currentValue)) {
            $textoObs = $currentValue . ' ' . $textoObs;
        }

        // El motivo de aprobación de Compras se resalta con el color elegido por la jefe (verde / naranja / rojo)
        $motivoCompras = trim((string) $pedido->motivo_aprobacion_compras);
        $colorHex = match ($pedido->color_motivo_compras) {
            'naranja' => 'FFF97316',
            'rojo' => 'FFDC2626',
            'verde' => 'FF16A34A',
            default => null,
        };
        $textoMotivo = 'Aprobación Compras: ' . $motivoCompras;
        $pos = ($colorHex && $motivoCompras !== '') ? strpos($textoObs, $textoMotivo) : false;

        if ($pos !== false) {
            $rich = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
            $antes = substr($textoObs, 0, $pos);
            $despues = substr($textoObs, $pos + strlen($textoMotivo));
            if ($antes !== '') {
                $rich->createText($antes);
            }
            $run = $rich->createTextRun($textoMotivo);
            $run->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($colorHex));
            if ($despues !== '') {
                $rich->createText($despues);
            }
            $sheet->getCell($obsCell)->setValue($rich);
        } else {
            $sheet->setCellValue($obsCell, $textoObs);
        }

        // Dynamic row height for observations
        // La columna de observaciones abarca B:K fusionadas (~90 chars de ancho aprox.)
        // Usamos un ancho más realista para no inflar la fila innecesariamente.
        $anchoAprox   = 90;   // caracteres por línea (columna B:K fusionada)
        $alturaLinea  = 13;   // pt por línea de texto
        $alturaMinima = 20;   // mínimo para que no quede aplastado
        $alturaMaxima = 100;  // máximo para mantener el pedido en 1 página cuando sea posible
        $lineas = 0;
        foreach (explode("\n", str_replace("\r\n", "\n", str_replace("\r", "\n", $textoObs))) as $parte) {
            $lineas += max(1, ceil(mb_strlen(trim($parte)) / $anchoAprox));
        }
        $lineas = max(1, $lineas);
        $alturaCalculada = $lineas * $alturaLinea;
        $sheet->getRowDimension($obsRow)->setRowHeight(
            max($alturaMinima, min($alturaCalculada, $alturaMaxima))
        );
    }

    // ─── Items ────────────────────────────────────────────────
    protected function llenarItems($items, int $startRow = 13): void
    {
        $sheet = $this->sheet;

        $count = 0;
        foreach ($items as $i => $item) {
            $row = $startRow + $i;
            $count++;

            $cellValue = $item->producto?->codigo ?? $count;

            $sheet->setCellValue("B{$row}", $cellValue);
            $sheet->setCellValue("C{$row}", $item->nombre);
            $sheet->setCellValue("I{$row}", $item->unidad_medida);
            $sheet->setCellValue("J{$row}", $item->cantidad);

            $sheet->getRowDimension($row)->setRowHeight(-1);
            $sheet->getStyle("C{$row}")->getAlignment()->setWrapText(true);
        }
    }

    // ─── Filas Extra ──────────────────────────────────────────
    protected function insertarFilasExtra(): void
    {
        $sheet = $this->sheet;
        $insertStart = 25;
        $sheet->insertNewRowBefore($insertStart, $this->extra);

        $startRow = 13 + 12;
        $endRow = $startRow + $this->extra - 1;

        for ($row = $startRow; $row <= $endRow; $row++) {
            $sheet->mergeCells("C{$row}:H{$row}");
            $sheet->mergeCells("J{$row}:K{$row}");

            $sheet->getStyle("C{$row}:H{$row}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);

            $sheet->getStyle("J{$row}:K{$row}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }
    }

    // ─── Firmas ───────────────────────────────────────────────
    protected function insertarFirmas(CpPedido $pedido): void
    {
        $offset = $this->extra;

        // Get raw paths (bypass accessors)
        $elaboradoFirma = $pedido->getRawOriginal('elaborado_por_firma');
        $comprasFirma = $pedido->getRawOriginal('proceso_compra_firma');
        $responsableFirma = $pedido->getRawOriginal('responsable_aprobacion_firma');

        // Configurar alturas armoniosas de las filas para no desfasar la página
        $sheet = $this->sheet;
        $sheet->getRowDimension(31 + $offset)->setRowHeight(15);
        $sheet->getRowDimension(32 + $offset)->setRowHeight(40);
        $sheet->getRowDimension(38 + $offset)->setRowHeight(15);
        $sheet->getRowDimension(39 + $offset)->setRowHeight(40);

        $this->insertarFirma($elaboradoFirma, 'B' . (32 + $offset), 30, 2);
        $this->insertarFirma($comprasFirma, 'B' . (39 + $offset), 20, 2);
        $this->insertarFirma($responsableFirma, 'G' . (39 + $offset), 20, 2);
    }

    protected function insertarFirma(?string $rutaFirma, string $celda, int $offsetX = 20, int $offsetY = 2): void
    {
        if (empty($rutaFirma)) return;

        // Limpiar el prefijo 'storage/' si viene en la ruta
        $rutaLimpia = preg_replace('/^storage\//', '', $rutaFirma);

        // Primero intentamos la ruta directa en el disco public
        $fullPath = Storage::disk('public')->path($rutaLimpia);

        // Si no existe, probamos si está en storage/app/public/signatures/ 
        if (!file_exists($fullPath)) {
            $fullPathAlter = Storage::disk('public')->path('signatures/' . basename($rutaLimpia));
            if (file_exists($fullPathAlter)) {
                $fullPath = $fullPathAlter;
            } else {
                // Descargar temporalmente desde el servidor de producción si no existe localmente
                $remoteUrl = "https://nexacoreapi.clinicalhouse.co/public/storage/" . $rutaLimpia;
                $cacheDir = storage_path('app/temp_signatures');
                if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
                $cacheFile = $cacheDir . '/' . md5($rutaLimpia) . '_' . basename($rutaLimpia);
                if (!file_exists($cacheFile)) {
                    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
                    $data = @file_get_contents($remoteUrl, false, $ctx);
                    if ($data) {
                        file_put_contents($cacheFile, $data);
                    }
                }
                if (file_exists($cacheFile)) {
                    $fullPath = $cacheFile;
                } else {
                    return;
                }
            }
        }

        try {
            // Validar que sea una imagen válida y soportada
            $imageInfo = @getimagesize($fullPath);
            if (!$imageInfo) {
                return; // No es una imagen válida o soportada
            }

            $drawing = new Drawing();
            $drawing->setName('Firma_' . $celda);
            $drawing->setDescription('Firma de Aprobacion');
            $drawing->setPath($fullPath);
            
            $drawing->setCoordinates($celda);
            $drawing->setHeight(36);
            $drawing->setResizeProportional(true);
            $drawing->setOffsetX($offsetX);
            $drawing->setOffsetY($offsetY);
            $drawing->setWorksheet($this->sheet);
        } catch (Exception $e) {
            // Ignorar el error de la imagen si ocurre para no romper la exportación
        }
    }

    // ─── Responsables ─────────────────────────────────────────
    protected function responsableProceso(CpPedido $pedido): void
    {
        $sheet = $this->sheet;
        $offset = $this->extra;

        $formatFecha = function ($fecha) {
            if (empty($fecha)) return '';
            return (new DateTime($fecha))->format('d/m/Y');
        };

        $concat = function ($cell, $value) use ($sheet) {
            if (!empty($value)) {
                $current = $sheet->getCell($cell)->getValue();
                $sheet->setCellValue($cell, !empty($current) ? $current . ' ' . $value : $value);
            }
        };

        // Fechas
        $concat('B' . (42 + $offset), $formatFecha($pedido->fecha_compra));
        $concat('G' . (42 + $offset), $formatFecha($pedido->fecha_gerencia));

        // Elaborado por
        $concat('B' . (33 + $offset), $pedido->elaboradoPor?->nombre_completo);
        $concat('B' . (34 + $offset), $pedido->elaboradoPor?->rol?->nombre);

        // Proceso compra
        $concat('B' . (40 + $offset), $pedido->procesoCompra?->nombre_completo);
        $concat('B' . (41 + $offset), $pedido->procesoCompra?->rol?->nombre);

        // Responsable aprobación
        $concat('G' . (40 + $offset), $pedido->responsableAprobacion?->nombre_completo);
        $concat('G' . (41 + $offset), $pedido->responsableAprobacion?->rol?->nombre);
    }

    protected function sanitize(string $value): string
    {
        // 1. Reemplazar caracteres con tilde por vocales normales y la eñe por n
        $unwanted_array = array(
            'Š' => 'S',
            'š' => 's',
            'Ž' => 'Z',
            'ž' => 'z',
            'À' => 'A',
            'Á' => 'A',
            'Â' => 'A',
            'Ã' => 'A',
            'Ä' => 'A',
            'Å' => 'A',
            'Æ' => 'A',
            'Ç' => 'C',
            'È' => 'E',
            'É' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Ì' => 'I',
            'Í' => 'I',
            'Î' => 'I',
            'Ï' => 'I',
            'Ñ' => 'N',
            'Ò' => 'O',
            'Ó' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ö' => 'O',
            'Ø' => 'O',
            'Ù' => 'U',
            'Ú' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'Ý' => 'Y',
            'Þ' => 'B',
            'ß' => 'Ss',
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ä' => 'a',
            'å' => 'a',
            'æ' => 'a',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ð' => 'o',
            'ñ' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ö' => 'o',
            'ø' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ý' => 'y',
            'þ' => 'b',
            'ÿ' => 'y'
        );
        $value = strtr($value, $unwanted_array);

        // 2. Permitir solo letras ascii, números, espacios, puntos y guiones
        return preg_replace('/[^a-zA-Z0-9_\-\. ]/', '_', $value);
    }

    // ─── Configuración de Página ──────────────────────────────
    protected function configurarPagina(): void
    {
        $sheet = $this->sheet;
        $lastRow = 42 + $this->extra;
        $sheet->getPageSetup()->setPrintArea("B2:K{$lastRow}");
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER);
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);
        if ($this->extra > 0) {
            $sheet->getPageSetup()->setFitToHeight(0);
        } else {
            $sheet->getPageSetup()->setFitToHeight(1);
        }
        $sheet->getPageSetup()->setHorizontalCentered(true);
        $sheet->getPageSetup()->setVerticalCentered(false);

        // Reducir la fila del texto legal (fila 28 en plantilla, con offset si hay items extra)
        // La plantilla tiene esa fila a 164pt; la bajamos a 90pt para que quepa en 1 página.
        $filaLegal = 28 + $this->extra;
        $alturaActualLegal = $sheet->getRowDimension($filaLegal)->getRowHeight();
        if ($alturaActualLegal < 0 || $alturaActualLegal > 90) {
            $sheet->getRowDimension($filaLegal)->setRowHeight(90);
        }

        $sheet->getPageMargins()->setTop(0.25);
        $sheet->getPageMargins()->setBottom(0.25);
        $sheet->getPageMargins()->setLeft(0.25);
        $sheet->getPageMargins()->setRight(0.25);
    }
}

