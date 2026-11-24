<?php

namespace App\Modules\GestionSistemas\Application\UseCases\EquiposComputo;

use App\Models\PcEntrega;
use App\Models\PcEquipo;
use App\Models\Personal;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as DrawingUnits;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Llena la plantilla oficial institucional "3503 PTSI-FO-01 HOJA DE VIDA DE EQUIPOS DE COMPUTO V2.xlsx".
 *
 * Mantiene intacta la estructura, diseño, cabeceras, alturas de fila, anchos de columna,
 * estilos de celda (fuentes Arial 11pt, alineaciones), cuadrícula y logotipo original del formato.
 */
class HojaVidaEquipoPlantilla
{
    public const PLANTILLA = '3503 PTSI-FO-01 HOJA DE VIDA DE EQUIPOS DE COMPUTO V2.xlsx';

    /** Relaciones necesarias para llenar la plantilla */
    public const RELACIONES = [
        'sede',
        'area',
        'responsable',
        'caracteristicasTecnicas.monitorInventario',
        'caracteristicasTecnicas.tecladoInventario',
        'caracteristicasTecnicas.mouseInventario',
    ];

    private const CAJA_IMAGEN_EQUIPO = 'F4:I14';
    private const CAJA_FIRMA = 'F21:I24';

    /** Primera fila del formato, debajo del encabezado (filas 1 a 3). */
    private const PRIMERA_FILA_FORMATO = 4;

    /** Archivos temporales creados para imágenes en base64 */
    private array $temporales = [];

    /** Caché en memoria para evitar consultas duplicadas */
    private array $cache = [];

    public static function rutaPlantilla(): string
    {
        $storagePath = storage_path('app/templates/' . self::PLANTILLA);
        if (!file_exists($storagePath)) {
            $downloadsPath = 'C:/Users/Usuario/Downloads/' . self::PLANTILLA;
            if (file_exists($downloadsPath)) {
                $dir = dirname($storagePath);
                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }
                @copy($downloadsPath, $storagePath);
            }
        }
        return $storagePath;
    }

    public function construir(PcEquipo $equipo, bool $paraPdf = false): Spreadsheet
    {
        $this->cache = [];

        $ruta = self::rutaPlantilla();
        if (!file_exists($ruta)) {
            throw new Exception('No se encontró la plantilla de hoja de vida (' . self::PLANTILLA . ').');
        }

        $spreadsheet = IOFactory::load($ruta);
        $sheet = $spreadsheet->getActiveSheet();

        // 1. Llenar los datos sin alterar formato ni textos de cabecera de la plantilla
        $this->llenarInformacionBasica($sheet, $equipo);
        $this->llenarCaracteristicasTecnicas($sheet, $equipo);
        $this->llenarUbicacion($sheet, $equipo);
        $this->marcarFormaAdquisicion($sheet, $equipo);
        $this->llenarTextosInferiores($sheet, $equipo, $paraPdf);

        if ($paraPdf) {
            // Menos margen arriba y abajo, y filas más altas para que el formato llene la hoja.
            // Va antes de las imágenes para que se centren en las casillas ya ampliadas.
            $sheet->getPageMargins()->setTop(0.3)->setBottom(0.3)->setHeader(0)->setFooter(0);
            // Textos largos (p. ej. una descripción sin espacios) se parten y su fila crece,
            // en vez de ensanchar la columna y achicar todo el formato
            $this->ajustarTextosLargosPdf($sheet, self::PRIMERA_FILA_FORMATO, $sheet->getHighestRow());
            $this->ampliarFilasParaPagina($sheet, self::PRIMERA_FILA_FORMATO, $sheet->getHighestRow());
        }

        // 2. Insertar fotografía del equipo y firma digital sin alterar el diseño
        $this->insertarImagen($sheet, $this->resolverImagen($equipo->imagen_url), self::CAJA_IMAGEN_EQUIPO, 'Imagen Equipo', 0.90);
        // La firma se recorta (sin bordes vacíos) para que quede centrada con márgenes iguales
        $this->insertarImagen($sheet, $this->recortarBordes($this->firmaResponsable($equipo)), self::CAJA_FIRMA, 'Firma Responsable', 0.80);

        // Espacio entre el encabezado (logo, título, código) y el formato.
        // Se inserta al final para no alterar las celdas usadas al llenar.
        $this->filaSeparadoraEncabezado($sheet);

        if ($paraPdf) {
            $this->filaGuiaAnchosPdf($sheet);
            $this->configurarImpresionPdf($sheet);
        }

        // 3. Garantizar que se mantenga la cuadrícula y selección limpia
        $sheet->setShowGridlines(true);
        $sheet->setSelectedCell('A1');

        return $spreadsheet;
    }

    /** Borra las imágenes temporales creadas al terminar el proceso */
    public function limpiarTemporales(): void
    {
        foreach ($this->temporales as $tmp) {
            @unlink($tmp);
        }
        $this->temporales = [];
    }

    // ------------------------------------------------------------------ Secciones

    private function llenarInformacionBasica(Worksheet $sheet, PcEquipo $equipo): void
    {
        $garantia = $equipo->garantia_meses
            ? $equipo->garantia_meses . ($equipo->garantia_meses == 1 ? ' mes' : ' meses')
            : '';

        $this->setValor($sheet, 'C5', $equipo->nombre_equipo);
        $this->setValor($sheet, 'C6', $equipo->descripcion_general);
        $this->setValor($sheet, 'C7', $equipo->marca);
        $this->setValor($sheet, 'C8', $equipo->serial);
        $this->setValor($sheet, 'C9', $equipo->tipo);
        $this->setValor($sheet, 'C10', optional($equipo->area)->nombre);
        $this->setValor($sheet, 'C11', $equipo->modelo);
        $this->setValor($sheet, 'C12', $equipo->numero_inventario);
        $this->setValor($sheet, 'C13', $garantia);
        $this->setValor($sheet, 'C14', $this->capitalizar($equipo->estado));
    }

    private function llenarCaracteristicasTecnicas(Worksheet $sheet, PcEquipo $equipo): void
    {
        $tec = $equipo->caracteristicasTecnicas;

        $this->setValor($sheet, 'C16', $tec?->procesador);
        $this->setValor($sheet, 'C17', trim(($tec?->disco_duro ?? '') . ' ' . ($tec?->capacidad_disco ?? '')));
        $this->setValor($sheet, 'C18', $equipo->ip_fija);
        $this->setValor($sheet, 'C19', $tec?->velocidad_red);
        $this->setValor($sheet, 'C20', $tec?->memoria_ram);
        $this->setValor($sheet, 'C21', $tec?->usb);
        $this->setValor($sheet, 'C22', $tec?->unidad_cd);
        $this->setValor($sheet, 'C23', $tec?->tarjeta_video);
        $this->setValor($sheet, 'C24', $tec?->tarjeta_red);
        $this->setValor($sheet, 'C25', $tec?->parlantes);
        $this->setValor($sheet, 'C26', $tec?->drive);
        $this->setValor($sheet, 'C27', $tec?->tarjeta_sonido);
    }

    private function llenarUbicacion(Worksheet $sheet, PcEquipo $equipo): void
    {
        $this->setValor($sheet, 'G16', optional($equipo->sede)->nombre);

        // La plantilla dice "DIRECCIÓN", pero el dato que corresponde es el departamento / área del equipo
        $sheet->setCellValue('F17', 'DEPARTAMENTO / ÁREA');
        $sheet->getStyle('F17')->getAlignment()->setWrapText(true);
        $this->setValor($sheet, 'G17', optional($equipo->area)->nombre);

        $this->setValor($sheet, 'G18', optional($this->personaResponsable($equipo))->nombre);
        $this->setValor($sheet, 'G19', $equipo->fecha_entrega ? $equipo->fecha_entrega->format('d/m/Y') : '');
    }

    private function marcarFormaAdquisicion(Worksheet $sheet, PcEquipo $equipo): void
    {
        $forma = $this->normalizar($equipo->forma_adquisicion);

        $celda = match (true) {
            str_contains($forma, 'COMPRA') => 'G26',
            str_contains($forma, 'DONACION') => 'I26',
            str_contains($forma, 'ALQUILER') => 'G27',
            str_contains($forma, 'COMODATO') => 'I27',
            default => null,
        };

        if ($celda) {
            $sheet->setCellValue($celda, 'X');
        }
    }

    private function llenarTextosInferiores(Worksheet $sheet, PcEquipo $equipo, bool $paraPdf): void
    {
        // Se plasman los datos reales almacenados en cada campo, sin inventar formatos ni prefijos
        $this->setTexto($sheet, 'B29', $equipo->equipos_adicionales, $paraPdf);
        $this->setTexto($sheet, 'F29', $equipo->recomendaciones, $paraPdf);
        $this->setTexto($sheet, 'B31', $equipo->observaciones, $paraPdf);
        $this->setTexto($sheet, 'F31', $equipo->repuestos_principales, $paraPdf);
    }

    // ------------------------------------------------------------------ Celdas

    private function setValor(Worksheet $sheet, string $celda, mixed $valor): void
    {
        $texto = trim((string) ($valor ?? ''));
        $sheet->setCellValueExplicit($celda, $texto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    }

    private function setTexto(Worksheet $sheet, string $celda, mixed $valor, bool $paraPdf): void
    {
        $texto = trim((string) ($valor ?? ''));
        if ($texto === '.') {
            $texto = '';
        }
        if ($paraPdf && $texto !== '') {
            $texto = preg_replace('/\s*\n+\s*/u', ' ', $texto);
        }

        $sheet->setCellValueExplicit($celda, $texto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->getStyle($celda)->getAlignment()->setWrapText(true);
    }

    // ------------------------------------------------------------------ Firma del responsable

    private function ultimaActa(PcEquipo $equipo): ?PcEntrega
    {
        if (!$equipo->id) {
            return null;
        }
        if (!array_key_exists('acta', $this->cache)) {
            $actas = PcEntrega::with('funcionario')
                ->where('equipo_id', $equipo->id)
                ->orderByDesc('fecha_entrega')
                ->orderByDesc('id')
                ->get();

            $this->cache['acta'] = ($equipo->responsable_id
                    ? $actas->firstWhere('funcionario_id', $equipo->responsable_id)
                    : null)
                ?? $actas->first();
        }
        return $this->cache['acta'];
    }

    private function personaResponsable(PcEquipo $equipo): ?Personal
    {
        return $equipo->responsable ?? $this->ultimaActa($equipo)?->funcionario;
    }

    private function firmaResponsable(PcEquipo $equipo): ?string
    {
        $persona = $this->personaResponsable($equipo);
        $candidatas = [];

        if ($persona) {
            try {
                $usuario = method_exists($persona, 'findUsuario') ? $persona->findUsuario() : null;
                if ($usuario) {
                    $candidatas[] = $usuario->getRawOriginal('firma_digital');
                }
            } catch (\Throwable $e) {
                // Continuar con siguientes opciones
            }
            $candidatas[] = $persona->getRawOriginal('firma') ?? $persona->firma ?? null;
        }

        try {
            $acta = $this->ultimaActa($equipo);
            if ($acta) {
                $candidatas[] = $acta->getRawOriginal('firma_recibe');
            }
        } catch (\Throwable $e) {
            // Sin actas
        }

        foreach ($candidatas as $c) {
            $ruta = $this->resolverImagen(is_string($c) ? trim($c) : null);
            if ($ruta && @getimagesize($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ Imágenes

    private function insertarImagen(Worksheet $sheet, ?string $ruta, string $caja, string $nombre, float $relleno): void
    {
        if (!$ruta || !file_exists($ruta)) {
            return;
        }
        $info = @getimagesize($ruta);
        if (!$info || $info[0] <= 0 || $info[1] <= 0) {
            return;
        }

        [$cajaW, $cajaH] = $this->tamanoCaja($sheet, $caja);
        $ratio = min(($cajaW * $relleno) / $info[0], ($cajaH * $relleno) / $info[1]);
        $w = max(1, (int) round($info[0] * $ratio));
        $h = max(1, (int) round($info[1] * $ratio));

        $drawing = new Drawing();
        $drawing->setName($nombre);
        $drawing->setDescription($nombre);
        $drawing->setPath($ruta);
        $drawing->setResizeProportional(false);
        $drawing->setWidth($w);
        $drawing->setHeight($h);
        $this->posicionarCentrado($sheet, $drawing, $caja, $w, $h);
        $drawing->setWorksheet($sheet);
    }

    /**
     * El convertidor de respaldo (mPDF) toma los anchos de columna de la primera fila de la
     * tabla. En esta plantilla esa fila tiene celdas combinadas (logo, título, código), así que
     * reparte los anchos según el texto y las dos columnas centrales quedan de distinto tamaño.
     * Se agrega arriba una fila de 1 pt, sin combinar ni bordes, que fija los anchos reales.
     * Va al final para no alterar las celdas usadas al llenar ni la posición de las imágenes.
     */
    /** Inserta una fila vacía entre el encabezado (filas 1 a 3) y el formato, sin bordes ni relleno. */
    private function filaSeparadoraEncabezado(Worksheet $sheet): void
    {
        $fila = self::PRIMERA_FILA_FORMATO;
        $ultima = $sheet->getHighestColumn();
        $primera = 'B';

        $sheet->insertNewRowBefore($fila, 1);
        $sheet->getStyle("A{$fila}:{$ultima}{$fila}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]],
            'fill' => ['fillType' => Fill::FILL_NONE],
        ]);
        $sheet->getRowDimension($fila)->setRowHeight(8);

        // Línea que cierra el encabezado y línea superior del formato
        $sheet->getStyle("{$primera}{$fila}:{$ultima}{$fila}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        $siguiente = $fila + 1;
        $sheet->getStyle("{$primera}{$siguiente}:{$ultima}{$siguiente}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
    }

    /**
     * El convertidor no puede partir una palabra sin espacios: ensancha la columna y reduce todo
     * el formato para que quepa. Aquí se insertan espacios en las palabras demasiado largas para
     * su casilla y se aumenta el alto de la fila según las líneas que ocupará el texto.
     */
    private function ajustarTextosLargosPdf(Worksheet $sheet, int $desde, int $hasta): void
    {
        $fuente = $sheet->getParent()->getDefaultStyle()->getFont();
        $anchoColPt = function (int $c) use ($sheet, $fuente): float {
            $ancho = $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->getWidth();
            return DrawingUnits::cellDimensionToPixels($ancho < 0 ? 8.43 : $ancho, $fuente) * 0.75;
        };

        $merges = [];
        foreach ($sheet->getMergeCells() as $rango) {
            [$ini, $fin] = Coordinate::rangeBoundaries($rango);
            $merges[Coordinate::stringFromColumnIndex($ini[0]) . $ini[1]] = [$ini, $fin];
        }

        $ultimaCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        for ($f = $desde; $f <= $hasta; $f++) {
            $altoNecesario = 0;
            for ($c = 1; $c <= $ultimaCol; $c++) {
                $coord = Coordinate::stringFromColumnIndex($c) . $f;
                $valor = $sheet->getCell($coord)->getValue();
                // Solo datos: las etiquetas de la plantilla (en negrita) no se tocan
                if (!is_string($valor) || trim($valor) === '' || $sheet->getStyle($coord)->getFont()->getBold()) {
                    continue;
                }

                [$ini, $fin] = $merges[$coord] ?? [[$c, $f], [$c, $f]];
                $anchoPt = 0;
                for ($k = $ini[0]; $k <= $fin[0]; $k++) {
                    $anchoPt += $anchoColPt($k);
                }
                $tamano = (float) ($sheet->getStyle($coord)->getFont()->getSize() ?: 11);
                $negrita = $sheet->getStyle($coord)->getFont()->getBold();
                $porLineaCon = fn (float $t) => max(4, (int) floor(($anchoPt - 6) / ($t * ($negrita ? 0.68 : 0.62))));
                $porLinea = $porLineaCon($tamano);

                // Texto muy largo: letra más pequeña (mínimo 6.5 pt) para que no alargue demasiado la hoja
                $maxAltoTexto = 60;
                while ($tamano > 6.5 && (mb_strlen($valor) / $porLinea) * $tamano * 1.25 > $maxAltoTexto) {
                    $tamano -= 0.5;
                    $porLinea = $porLineaCon($tamano);
                }
                if ($tamano < (float) ($sheet->getStyle($coord)->getFont()->getSize() ?: 11)) {
                    $sheet->getStyle($coord)->getFont()->setSize($tamano);
                }

                // Si aun así no cabe en una hoja, en el PDF se muestra hasta donde cabe (el texto
                // completo sigue en el sistema y en el Excel)
                $maxCaracteres = (int) floor($maxAltoTexto / ($tamano * 1.25)) * $porLinea;
                if ($ini[1] === $fin[1] && mb_strlen($valor) > $maxCaracteres) {
                    $valor = rtrim(mb_substr($valor, 0, max(1, $maxCaracteres - 1))) . '…';
                    $sheet->getCell($coord)->setValueExplicit($valor, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }

                // Partir solo las palabras que no caben en la casilla, en trozos con holgura
                // (las letras anchas ocupan más de lo estimado)
                $corte = max(20, (int) floor($porLinea * 0.7));
                $nuevo = preg_replace_callback('/\S{' . (max($corte, $porLinea) + 1) . ',}/u', function ($m) use ($corte) {
                    return implode(' ', mb_str_split($m[0], $corte));
                }, $valor);
                if ($nuevo !== $valor) {
                    $sheet->getCell($coord)->setValueExplicit($nuevo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->getStyle($coord)->getAlignment()->setWrapText(true);
                }

                // Solo las casillas de una fila definen el alto de esa fila
                if ($ini[1] !== $fin[1]) {
                    continue;
                }
                $lineas = 0;
                foreach (preg_split('/\n/u', $nuevo) as $parrafo) {
                    $lineas += max(1, (int) ceil(mb_strlen($parrafo) / $porLinea));
                }
                if ($lineas > 1) {
                    $altoNecesario = max($altoNecesario, $lineas * $tamano * 1.25 + 4);
                }
            }
            if ($altoNecesario > $this->altoFila($sheet, $f)) {
                $sheet->getRowDimension($f)->setRowHeight(round($altoNecesario, 2));
            }
        }
    }

    /**
     * Amplía en la misma proporción los altos de las filas indicadas para que el formato,
     * ya ajustado al ancho de la hoja carta vertical, ocupe también el alto disponible.
     */
    private function ampliarFilasParaPagina(Worksheet $sheet, int $desde, int $hasta): void
    {
        $fuente = $sheet->getParent()->getDefaultStyle()->getFont();
        $ultimaCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        $anchoPt = 0;
        for ($c = 1; $c <= $ultimaCol; $c++) {
            $ancho = $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->getWidth();
            $anchoPt += DrawingUnits::cellDimensionToPixels($ancho < 0 ? 8.43 : $ancho, $fuente) * 0.75;
        }

        $m = $sheet->getPageMargins();
        $anchoUtil = (8.5 - $m->getLeft() - $m->getRight()) * 72;
        $altoUtil = (11 - $m->getTop() - $m->getBottom()) * 72;
        // El convertidor reduce la tabla para que quepa a lo ancho; el alto se reduce igual
        $reduccion = $anchoPt > $anchoUtil ? $anchoUtil / $anchoPt : 1;
        // Margen para la fila guía, la fila separadora y los bordes
        $objetivo = $altoUtil / $reduccion - 20;

        $total = 0;
        $variable = 0;
        for ($f = 1; $f <= $hasta; $f++) {
            $alto = $this->altoFila($sheet, $f);
            $total += $alto;
            if ($f >= $desde) {
                $variable += $alto;
            }
        }
        if ($variable <= 0 || $total >= $objetivo) {
            return;
        }

        $factor = ($variable + ($objetivo - $total)) / $variable;
        for ($f = $desde; $f <= $hasta; $f++) {
            $sheet->getRowDimension($f)->setRowHeight(round($this->altoFila($sheet, $f) * $factor, 2));
        }
    }

    /**
     * Configuración de impresión explícita: así el PDF sale igual con el microservicio
     * (LibreOffice, usado en producción y en el correo del acta) y con el convertidor local:
     * una sola hoja carta vertical, ajustada al ancho y centrada.
     */
    private function configurarImpresionPdf(Worksheet $sheet): void
    {
        $setup = $sheet->getPageSetup();
        $setup->setPrintArea('A1:' . $sheet->getHighestColumn() . $sheet->getHighestRow());
        $setup->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $setup->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $setup->setFitToPage(true);
        $setup->setFitToWidth(1);
        $setup->setFitToHeight(1);
        $setup->setHorizontalCentered(true);
        $sheet->setShowGridlines(false);
        $sheet->setPrintGridlines(false);
    }

    private function filaGuiaAnchosPdf(Worksheet $sheet): void
    {
        $ultima = $sheet->getHighestColumn();
        $sheet->insertNewRowBefore(1, 1);
        $sheet->getStyle("A1:{$ultima}1")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]],
            'fill' => ['fillType' => Fill::FILL_NONE],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(1);
    }

    private function anchoColumnaPx(Worksheet $sheet, int $columna): int
    {
        $ancho = $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columna))->getWidth();
        if ($ancho < 0) {
            $ancho = 8.43;
        }
        $mdw = 7;
        return (int) floor(((256 * $ancho + floor(128 / $mdw)) / 256) * $mdw);
    }

    private function altoFila(Worksheet $sheet, int $fila): float
    {
        $alto = $sheet->getRowDimension($fila)->getRowHeight();
        return $alto > 0 ? $alto : 15.0;
    }

    private function tamanoCaja(Worksheet $sheet, string $rango): array
    {
        [$ini, $fin] = Coordinate::rangeBoundaries($rango);

        $w = 0;
        for ($c = $ini[0]; $c <= $fin[0]; $c++) {
            $w += $this->anchoColumnaPx($sheet, $c);
        }
        $h = 0;
        for ($f = $ini[1]; $f <= $fin[1]; $f++) {
            $h += DrawingUnits::pointsToPixels($this->altoFila($sheet, $f));
        }
        return [$w, $h];
    }

    private function posicionarCentrado(Worksheet $sheet, Drawing $drawing, string $rango, int $w, int $h): void
    {
        [$ini] = Coordinate::rangeBoundaries($rango);
        [$cajaW, $cajaH] = $this->tamanoCaja($sheet, $rango);

        // Anclada en la primera celda del recuadro (el convertidor de respaldo solo dibuja
        // imágenes de esa celda) y desplazada para quedar en el centro del recuadro.
        $ancla = Coordinate::stringFromColumnIndex($ini[0]) . $ini[1];
        $drawing->setCoordinates($ancla);
        $drawing->setOffsetX(max(0, (int) round(($cajaW - $w) / 2)));
        $drawing->setOffsetY(max(0, (int) round(($cajaH - $h) / 2)));

        // El convertidor de respaldo ubica la imagen según la alineación de la celda: centrada
        $sheet->getStyle($rango)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * Devuelve una copia de la imagen sin los bordes blancos o transparentes, para que al
     * centrarla queden márgenes iguales. Si no se puede procesar, devuelve la original.
     */
    private function recortarBordes(?string $ruta): ?string
    {
        if (!$ruta || !function_exists("imagecreatefromstring")) {
            return $ruta;
        }
        $img = @imagecreatefromstring((string) @file_get_contents($ruta));
        if (!$img) {
            return $ruta;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $vacio = function (int $x, int $y) use ($img): bool {
            $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
            return $c["alpha"] > 100 || ($c["red"] > 235 && $c["green"] > 235 && $c["blue"] > 235);
        };

        $paso = max(1, (int) floor(min($w, $h) / 400));
        $top = $h; $bottom = -1; $left = $w; $right = -1;
        for ($y = 0; $y < $h; $y += $paso) {
            for ($x = 0; $x < $w; $x += $paso) {
                if (!$vacio($x, $y)) {
                    $top = min($top, $y); $bottom = max($bottom, $y);
                    $left = min($left, $x); $right = max($right, $x);
                }
            }
        }
        if ($bottom < 0) {
            imagedestroy($img);
            return $ruta; // imagen vacía
        }

        $m = 2 * $paso;
        $left = max(0, $left - $m); $top = max(0, $top - $m);
        $right = min($w - 1, $right + $m); $bottom = min($h - 1, $bottom + $m);
        $cw = $right - $left + 1;
        $ch = $bottom - $top + 1;
        if ($cw >= $w - 2 && $ch >= $h - 2) {
            imagedestroy($img);
            return $ruta; // no hay bordes que quitar
        }

        $rec = imagecreatetruecolor($cw, $ch);
        imagealphablending($rec, false);
        imagesavealpha($rec, true);
        imagefill($rec, 0, 0, imagecolorallocatealpha($rec, 255, 255, 255, 127));
        imagecopy($rec, $img, 0, 0, $left, $top, $cw, $ch);

        $destino = tempnam(sys_get_temp_dir(), "firma_") . ".png";
        imagepng($rec, $destino);
        imagedestroy($img);
        imagedestroy($rec);
        $this->temporales[] = $destino;

        return $destino;
    }

    private function resolverImagen(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'data:image')) {
            if (preg_match('/^data:image\/(\w+);base64,/', $path, $tipo)) {
                $datos = base64_decode(substr($path, strpos($path, ',') + 1), true);
                if ($datos !== false) {
                    $tmp = tempnam(sys_get_temp_dir(), 'hv_img_');
                    $conExt = $tmp . '.' . strtolower($tipo[1]);
                    @unlink($tmp);
                    file_put_contents($conExt, $datos);
                    $this->temporales[] = $conExt;
                    return $conExt;
                }
            }
            return null;
        }

        $limpia = $path;
        if (preg_match('#/storage/(.+)#', $limpia, $m)) {
            $limpia = $m[1];
        }
        $limpia = ltrim(str_replace(['public/', 'storage/', 'api/'], '', $limpia), '/');

        foreach ([
            storage_path('app/public/' . $limpia),
            public_path('storage/' . $limpia),
            storage_path('app/' . $limpia),
        ] as $candidata) {
            if (file_exists($candidata)) {
                return $candidata;
            }
        }

        return Storage::disk('public')->exists($limpia) ? Storage::disk('public')->path($limpia) : null;
    }

    // ------------------------------------------------------------------ Utilidades

    private function capitalizar(?string $v): string
    {
        $v = trim((string) $v);
        return $v === '' ? '' : mb_strtoupper(mb_substr($v, 0, 1)) . mb_substr($v, 1);
    }

    private function normalizar(?string $v): string
    {
        $v = mb_strtoupper(trim((string) $v));
        return strtr($v, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']);
    }
}
