<?php

namespace App\Modules\GestionInfraestructura\Application\UseCases\Equipos;

use App\Models\InfraEquipo;
use App\Models\Mantenimiento;
use App\Models\Sede;
use App\Models\Usuario;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
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
 * Llena la plantilla oficial "plantilla_hv_infraestructura.xlsx", que contiene dos hojas:
 *  - PLANTILLA HV INFRAESTRUCTURA (PIT-FO-37): hoja de vida del equipo (aires y otros).
 *  - PLANTILLA MANTENIMIENTO (PTSI-FO-04): reporte de mantenimientos del equipo.
 *
 * Solo se escriben valores en las celdas de datos: cabeceras, logos, bordes, fuentes y
 * celdas combinadas de la plantilla se conservan tal cual.
 */
class InfraEquipoPlantilla
{
    public const PLANTILLA = 'plantilla_hv_infraestructura.xlsx';

    public const HOJA_HV = 'PLANTILLA HV INFRAESTRUCTURA';
    public const HOJA_MANTENIMIENTO = 'PLANTILLA MANTENIMIENTO';

    public const RELACIONES = [
        'sede',
        'area',
        'responsable',
        'proveedor',
        'trasladoSede',
        'trasladoArea',
    ];

    private const CAJA_FOTO = 'C8:P20';

    /** Filas de detalle que trae la plantilla de mantenimiento (11 a 17) */
    private const MANT_PRIMERA_FILA = 11;
    private const MANT_FILAS_PLANTILLA = 7;
    private const MANT_ALTO_FILA = 55.05;
    private const MANT_MERGES = ['B:C', 'F:G', 'H:I', 'J:N', 'O:S', 'V:W', 'X:AB', 'AC:AE', 'AF:AH'];

    private array $temporales = [];

    /** Responsables de sede ya consultados (sede_id => Usuario|null) */
    private array $responsablesSede = [];
    private ?object $empresa = null;
    private bool $empresaCargada = false;

    public static function rutaPlantilla(): string
    {
        return storage_path('app/templates/' . self::PLANTILLA);
    }

    // ================================================================== API pública

    public function construirHojaVida(InfraEquipo $equipo, bool $paraPdf = false): Spreadsheet
    {
        $spreadsheet = $this->cargarPlantilla(self::HOJA_HV);
        $sheet = $spreadsheet->getActiveSheet();

        $this->hvEmpresa($sheet, $equipo);
        $this->hvEquipo($sheet, $equipo);
        $this->hvUbicacionYAdquisicion($sheet, $equipo);
        $this->hvRegistroHistorico($sheet, $equipo);
        $this->hvTextos($sheet, $equipo, $paraPdf);

        // Ocupar toda la hoja carta vertical (los altos de fila se amplían en la misma proporción)
        $this->escalarAltoParaPagina($sheet, 'A1:AQ42', 7.9, 10.1);
        $this->reubicarDibujosPlantilla($sheet);

        $this->insertarImagen($sheet, $this->resolverImagen($equipo->imagen_url), self::CAJA_FOTO, 'Foto Equipo', 0.92);

        $this->ajustarImpresion($sheet, 'A1:AQ42', unaPagina: true, vertical: true);

        return $spreadsheet;
    }

    /**
     * @param \Illuminate\Support\Collection|null $mantenimientos Si es null se buscan todos los del equipo
     */
    public function construirMantenimientos(InfraEquipo $equipo, bool $paraPdf = false, ?\Illuminate\Support\Collection $mantenimientos = null): Spreadsheet
    {
        $spreadsheet = $this->cargarPlantilla(self::HOJA_MANTENIMIENTO);
        $sheet = $spreadsheet->getActiveSheet();
        $this->reubicarDibujosPlantilla($sheet);

        $empresa = $this->empresa();
        $this->setValor($sheet, 'H6', $empresa->nombre ?? '');
        $this->setValor($sheet, 'H7', $equipo->activo_fijo);
        $this->setValor($sheet, 'O7', $equipo->marca);
        $this->setValor($sheet, 'X7', $equipo->modelo);
        $this->setValor($sheet, 'AH7', $equipo->serial);
        $this->setValor($sheet, 'H8', $this->ubicacion($equipo));
        $this->setValor($sheet, 'AH8', optional($equipo->sede)->nombre);

        $mantenimientos = ($mantenimientos ?? (new InfraEquipoMantenimientoResolver())->mantenimientosDeEquipo($equipo))
            ->sortBy(fn ($m) => [$this->fechaMantenimiento($m)?->timestamp ?? 0, $m->id])
            ->values();

        $ultimaFila = $this->prepararFilasMantenimiento($sheet, $mantenimientos->count());

        foreach ($mantenimientos as $i => $mant) {
            $this->llenarFilaMantenimiento($sheet, self::MANT_PRIMERA_FILA + $i, $mant, $paraPdf, $equipo);
        }

        // Fila de separación entre el encabezado (logo, título, código) y el formato, como en la HV.
        // Se inserta al final para no alterar las referencias de celdas usadas arriba.
        $sheet->insertNewRowBefore(6, 1);
        $sheet->getStyle('A6:AI6')->applyFromArray([
            'borders' => [
                'left' => ['borderStyle' => Border::BORDER_NONE],
                'right' => ['borderStyle' => Border::BORDER_NONE],
                'inside' => ['borderStyle' => Border::BORDER_NONE],
            ],
            'fill' => ['fillType' => Fill::FILL_NONE],
        ]);
        $sheet->getStyle('A6:AI6')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_NONE);
        $sheet->getStyle('A6:AI6')->getBorders()->getTop()->setBorderStyle(Border::BORDER_NONE);
        $sheet->getStyle('AI7')->getBorders()->getTop()->setBorderStyle(Border::BORDER_NONE);
        // Cierre del encabezado y borde superior del formato, solo en el ancho del formato (B:AH)
        $sheet->getStyle('B6:AH6')->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('B7:AH7')->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getRowDimension(6)->setRowHeight(9);

        $this->ajustarImpresion($sheet, 'A1:AI' . ($ultimaFila + 2));

        return $spreadsheet;
    }

    public function limpiarTemporales(): void
    {
        foreach ($this->temporales as $tmp) {
            @unlink($tmp);
        }
        $this->temporales = [];
    }

    // ================================================================== Hoja de vida

    private function hvEmpresa(Worksheet $sheet, InfraEquipo $equipo): void
    {
        $empresa = $this->empresa();

        $this->setValor($sheet, 'Y10', optional($equipo->sede)->nombre);
        $this->setValor($sheet, 'V11', $empresa->direccion ?? '');
        $this->setValor($sheet, 'V12', $empresa->telefono ?? '');
        $this->setValor($sheet, 'AG12', $empresa->email ?? '');
    }

    private function hvEquipo(Worksheet $sheet, InfraEquipo $equipo): void
    {
        $this->setValor($sheet, 'AA16', $equipo->nombre_equipo);
        $this->setValor($sheet, 'X17', $equipo->serial);
        $this->setValor($sheet, 'AJ17', $equipo->capacidad);
        $this->setValor($sheet, 'V18', $equipo->marca);
        $this->setValor($sheet, 'AG18', $equipo->modelo);
        $this->setValor($sheet, 'V19', $equipo->voltaje);
        $this->setValor($sheet, 'AC19', $equipo->amperaje);
        $this->setValor($sheet, 'AK19', $equipo->frecuencia);
        $this->setValor($sheet, 'W20', $equipo->tipo_equipo);
        $this->setValor($sheet, 'AJ20', $equipo->activo_fijo);

        // Celdas estrechas: reducir texto para que no se corte
        foreach (['X17', 'AJ17', 'V18', 'AG18', 'V19', 'AC19', 'AK19', 'W20', 'AJ20', 'V11', 'AG12'] as $celda) {
            $sheet->getStyle($celda)->getAlignment()->setShrinkToFit(true);
        }
    }

    private function hvUbicacionYAdquisicion(Worksheet $sheet, InfraEquipo $equipo): void
    {
        $this->setValor($sheet, 'E23', $this->ubicacion($equipo));
        $sheet->getStyle('E23')->getAlignment()->setShrinkToFit(true);

        $forma = $this->normalizar($equipo->forma_adquisicion);
        $celda = match (true) {
            str_contains($forma, 'COMPRA') => 'W23',
            str_contains($forma, 'ALQUILER') => 'AC23',
            str_contains($forma, 'DONACION') => 'AH23',
            str_contains($forma, 'COMODATO') => 'AN23',
            default => null,
        };
        if ($celda) {
            $this->marcarX($sheet, $celda);
        }

        $this->setValor($sheet, 'C25', $equipo->frecuencia_mantenimiento);

        $proveedor = $equipo->proveedor;
        $this->setValor($sheet, 'W25', $proveedor?->nombre);
        $this->setValor($sheet, 'AJ25', $proveedor?->telefono);
        $this->setValor($sheet, 'W26', $proveedor?->direccion);

        $responsable = $equipo->responsable;
        $nombreResponsable = $responsable
            ? trim(($responsable->nombre ?? '') . ' ' . ($responsable->apellidos ?? ''))
            : '';
        $this->setValor($sheet, 'G26', $nombreResponsable);

        foreach (['W25', 'AJ25', 'W26', 'G26'] as $c) {
            $sheet->getStyle($c)->getAlignment()->setShrinkToFit(true);
        }
    }

    private function hvRegistroHistorico(Worksheet $sheet, InfraEquipo $equipo): void
    {
        $this->setValor($sheet, 'I29', $this->fecha($equipo->fecha_compra));
        $this->setValor($sheet, 'X29', $equipo->garantia_meses
            ? $equipo->garantia_meses . ($equipo->garantia_meses == 1 ? ' mes' : ' meses')
            : '');
        $this->setValor($sheet, 'AJ29', $this->fecha($equipo->fecha_instalacion));
        $this->setValor($sheet, 'I30', $equipo->vida_util_anos
            ? $equipo->vida_util_anos . ($equipo->vida_util_anos == 1 ? ' año' : ' años')
            : '');

        // Traslados: fecha, sede y área de origen (la ubicación actual está en la sección superior)
        $this->setValor($sheet, 'P32', 'SEDE ORIGEN');
        $this->setValor($sheet, 'I32', $this->fecha($equipo->fecha_traslado));
        $this->setValor($sheet, 'X32', $equipo->fecha_traslado ? optional($equipo->trasladoSede)->nombre : '');
        $this->setValor($sheet, 'AH32', $equipo->fecha_traslado ? optional($equipo->trasladoArea)->nombre : '');
        foreach (['X32', 'AH32'] as $c) {
            $sheet->getStyle($c)->getAlignment()->setShrinkToFit(true);
        }

        // Estado: NUEVO / USADO y el estado detallado en OBSERVACION
        $estado = strtolower(trim((string) $equipo->estado_equipo));
        if ($estado !== '') {
            $this->marcarX($sheet, $estado === 'nuevo' ? 'V33' : 'N33');
        }
        $this->setValor($sheet, 'AD33', $this->etiquetaEstado($estado));
    }

    private function hvTextos(Worksheet $sheet, InfraEquipo $equipo, bool $paraPdf): void
    {
        $observaciones = collect([
            $equipo->descripcion_general ? 'Descripción: ' . trim($equipo->descripcion_general) : null,
            $equipo->observaciones ? trim($equipo->observaciones) : null,
        ])->filter()->implode("\n");

        $this->setTexto($sheet, 'C35', $observaciones, $paraPdf, 'C', 'AO');
        $this->setTexto($sheet, 'C37', $equipo->recomendaciones, $paraPdf, 'C', 'AO');
        $this->setTexto($sheet, 'C39', $equipo->equipos_adicionales, $paraPdf, 'C', 'AO');
        $this->setTexto($sheet, 'C41', $equipo->repuestos_principales, $paraPdf, 'C', 'AO');
    }

    // ================================================================== Mantenimientos

    /**
     * Si hay más mantenimientos que filas en la plantilla, se insertan filas nuevas
     * replicando estilo, alto y celdas combinadas de la última fila de detalle.
     * Devuelve la última fila de la tabla.
     */
    private function prepararFilasMantenimiento(Worksheet $sheet, int $total): int
    {
        $ultimaPlantilla = self::MANT_PRIMERA_FILA + self::MANT_FILAS_PLANTILLA - 1; // 17
        $extra = max(0, $total - self::MANT_FILAS_PLANTILLA);

        if ($extra === 0) {
            return $ultimaPlantilla;
        }

        $sheet->insertNewRowBefore($ultimaPlantilla + 1, $extra);

        $colMax = Coordinate::columnIndexFromString('AH');
        for ($fila = $ultimaPlantilla + 1; $fila <= $ultimaPlantilla + $extra; $fila++) {
            for ($c = 2; $c <= $colMax; $c++) {
                $col = Coordinate::stringFromColumnIndex($c);
                $sheet->duplicateStyle($sheet->getStyle($col . $ultimaPlantilla), $col . $fila);
            }
            foreach (self::MANT_MERGES as $rango) {
                [$a, $b] = explode(':', $rango);
                $sheet->mergeCells("{$a}{$fila}:{$b}{$fila}");
            }
            $sheet->getRowDimension($fila)->setRowHeight(self::MANT_ALTO_FILA);
        }

        return $ultimaPlantilla + $extra;
    }

    private function llenarFilaMantenimiento(Worksheet $sheet, int $fila, Mantenimiento $mant, bool $paraPdf, InfraEquipo $equipo): void
    {
        $fecha = $this->fechaMantenimiento($mant);
        if ($fecha) {
            $this->setValor($sheet, "B{$fila}", $fecha->format('Y'));
            $this->setValor($sheet, "D{$fila}", $fecha->format('m'));
            $this->setValor($sheet, "E{$fila}", $fecha->format('d'));
        }

        // Tipo de mantenimiento: correctivo si así se indica en el título/descripción; si no, preventivo
        $texto = $this->normalizar(($mant->titulo ?? '') . ' ' . ($mant->descripcion ?? ''));
        $celdaTipo = str_contains($texto, 'CORRECTIV') ? "H{$fila}" : "F{$fila}";
        $this->marcarX($sheet, $celdaTipo);

        $descripcion = trim((string) ($mant->descripcion ?? '')) ?: trim((string) ($mant->titulo ?? ''));

        $obs = [];
        if ($mant->titulo && trim($mant->titulo) !== $descripcion) {
            $obs[] = trim($mant->titulo);
        }
        if ($mant->codigo) {
            $obs[] = 'Código: ' . $mant->codigo;
        }
        if ($mant->esta_revisado) {
            $revisor = $mant->revisador?->nombre_completo;
            $obs[] = 'Revisado' . ($mant->fecha_revisado ? ' el ' . $mant->fecha_revisado->format('d/m/Y') : '')
                . ($revisor ? ' por ' . $revisor : '');
        } else {
            $obs[] = 'Pendiente de revisión';
        }

        $this->setTexto($sheet, "J{$fila}", $descripcion, $paraPdf, 'J', 'N', false);
        $this->setTexto($sheet, "O{$fila}", implode($paraPdf ? ' | ' : "\n", $obs), $paraPdf, 'O', 'S', false);

        foreach (['B', 'D', 'E', 'F', 'H', 'J', 'O'] as $col) {
            $al = $sheet->getStyle("{$col}{$fila}")->getAlignment();
            $al->setVertical(Alignment::VERTICAL_CENTER);
            if (in_array($col, ['J', 'O'], true)) {
                $al->setHorizontal(Alignment::HORIZONTAL_LEFT);
            } else {
                $al->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        // Alto de fila según el texto más largo
        $lineas = max(
            $this->lineasEstimadas($sheet, $descripcion, 'J', 'N', 9),
            $this->lineasEstimadas($sheet, implode("\n", $obs), 'O', 'S', 9)
        );
        $sheet->getRowDimension($fila)->setRowHeight(max(self::MANT_ALTO_FILA, min(400, $lineas * 12 + 6)));
        foreach (['J', 'O'] as $col) {
            $rango = $col === 'J' ? "J{$fila}:N{$fila}" : "O{$fila}:S{$fila}";
            $sheet->getStyle($rango)->getFont()->setName('Calibri')->setSize(9)->setBold(false)->getColor()->setARGB('FF000000');
            $sheet->getStyle($rango)->getAlignment()->setShrinkToFit(false)->setWrapText(true);
        }

        // Firmas: personal a cargo (responsable de la sede donde se hizo el mantenimiento; si la sede
        // no tiene responsable, el coordinador del mantenimiento) y personal de infraestructura (técnico)
        $this->firmaOCelda($sheet, $this->responsableSede($mant->sede_id ?: $equipo->sede_id) ?? $mant->coordinador, "AC{$fila}:AE{$fila}");
        $this->firmaOCelda($sheet, $mant->creador, "AF{$fila}:AH{$fila}");
    }

    private function responsableSede(?int $sedeId): ?Usuario
    {
        if (!$sedeId) {
            return null;
        }
        if (!array_key_exists($sedeId, $this->responsablesSede)) {
            $this->responsablesSede[$sedeId] = Sede::with("responsable")->find($sedeId)?->responsable;
        }
        return $this->responsablesSede[$sedeId];
    }

    private function firmaOCelda(Worksheet $sheet, ?Usuario $usuario, string $caja): void
    {
        if (!$usuario) {
            return;
        }

        $firma = $this->firmaUsuario($usuario);
        $celda = explode(':', $caja)[0];

        if ($firma) {
            // La firma ocupa el centro de la casilla; el nombre queda abajo
            $this->insertarImagen($sheet, $firma, $caja, 'Firma ' . $celda, 0.62);
        }

        // Con firma, el salto de línea inicial deja el nombre en su propia línea, debajo de la imagen
        $nombre = trim((string) ($usuario->nombre_completo ?? ''));
        $sheet->setCellValueExplicit($celda, ($firma && $nombre !== '' ? "\n" : '') . $nombre, DataType::TYPE_STRING);
        $al = $sheet->getStyle($caja)->getAlignment();
        $al->setWrapText(true)->setVertical(Alignment::VERTICAL_BOTTOM)->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($celda)->getFont()->setName('Calibri')->setSize(8);
    }

    private function firmaUsuario(Usuario $usuario): ?string
    {
        $candidatas = [];
        try {
            $candidatas[] = $usuario->getRawOriginal('firma_digital');
            $candidatas[] = $usuario->firma_digital; // incluye respaldo desde tabla personal
        } catch (\Throwable $e) {
            // sin firma
        }

        foreach ($candidatas as $c) {
            $ruta = $this->resolverImagen(is_string($c) ? trim($c) : null);
            if ($ruta && @getimagesize($ruta)) {
                return $ruta;
            }
        }
        return null;
    }

    private function fechaMantenimiento(Mantenimiento $m): ?Carbon
    {
        $f = $m->fecha_creacion ?? $m->fecha_ultima_actualizacion;
        if (!$f) {
            return null;
        }
        return $f instanceof Carbon ? $f : Carbon::parse($f);
    }

    // ================================================================== Plantilla / impresión

    private function cargarPlantilla(string $hoja): Spreadsheet
    {
        $ruta = self::rutaPlantilla();
        if (!file_exists($ruta)) {
            throw new Exception('No se encontró la plantilla de infraestructura (' . self::PLANTILLA . ').');
        }

        $spreadsheet = IOFactory::load($ruta);
        $indice = $spreadsheet->getIndex($spreadsheet->getSheetByName($hoja)
            ?? throw new Exception("La plantilla no contiene la hoja '{$hoja}'."));

        // Dejar solo la hoja solicitada (el convertidor a PDF procesa el libro completo)
        for ($i = $spreadsheet->getSheetCount() - 1; $i >= 0; $i--) {
            if ($i !== $indice) {
                $spreadsheet->removeSheetByIndex($i);
            }
        }
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Amplía proporcionalmente los altos de fila del rango para que la proporción alto/ancho
     * del formato coincida con la del área útil de la hoja (pulgadas). Así, al ajustarse al
     * ancho de la página, el formato ocupa también todo el alto.
     */
    private function escalarAltoParaPagina(Worksheet $sheet, string $rango, float $anchoUtil, float $altoUtil): void
    {
        [$ini, $fin] = Coordinate::rangeBoundaries($rango);

        $anchoPx = 0;
        for ($c = $ini[0]; $c <= $fin[0]; $c++) {
            $anchoPx += $this->anchoColumnaPx($sheet, $c);
        }
        $altoPx = 0;
        for ($f = $ini[1]; $f <= $fin[1]; $f++) {
            $altoPx += DrawingUnits::pointsToPixels($this->altoFila($sheet, $f));
        }
        if ($anchoPx <= 0 || $altoPx <= 0) {
            return;
        }

        $altoObjetivo = $anchoPx * ($altoUtil / $anchoUtil) * 0.97;
        $factor = $altoObjetivo / $altoPx;
        if ($factor <= 1.02) {
            return; // ya ocupa la hoja (p. ej. textos largos)
        }

        for ($f = $ini[1]; $f <= $fin[1]; $f++) {
            $sheet->getRowDimension($f)->setRowHeight(round($this->altoFila($sheet, $f) * $factor, 2));
        }
    }

    private function ajustarImpresion(Worksheet $sheet, string $areaImpresion, bool $unaPagina = false, bool $vertical = false): void
    {
        $setup = $sheet->getPageSetup();
        $setup->setPrintArea($areaImpresion);

        // Todo el contenido centrado verticalmente en su celda
        $sheet->getStyle($areaImpresion)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        // Excepto las casillas con firma: la imagen va en el centro y el nombre debajo
        foreach ($sheet->getDrawingCollection() as $dibujo) {
            if (str_starts_with($dibujo->getName(), 'Firma ')) {
                $sheet->getStyle($dibujo->getCoordinates())->getAlignment()->setVertical(Alignment::VERTICAL_BOTTOM);
            }
        }

        // Quitar columnas fuera del área de impresión (la plantilla trae una celda suelta en AT18):
        // el convertidor de respaldo no respeta el área y encogería el formato para incluirlas
        $ultimaCol = Coordinate::columnIndexFromString(preg_replace('/\d+/', '', explode(':', $areaImpresion)[1]));
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        if ($maxCol > $ultimaCol) {
            $sheet->removeColumnByIndex($ultimaCol + 1, $maxCol - $ultimaCol);
        }
        // Hoja de vida: vertical (carta). Reporte de mantenimientos: horizontal
        $setup->setOrientation($vertical ? PageSetup::ORIENTATION_PORTRAIT : PageSetup::ORIENTATION_LANDSCAPE);
        $setup->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $setup->setFitToPage(true);
        $setup->setFitToWidth(1);
        $setup->setFitToHeight($unaPagina ? 1 : 0);
        $setup->setHorizontalCentered(true);

        // Márgenes laterales iguales al espacio sobrante: la tabla queda centrada sin depender
        // del centrado del convertidor (que no desplaza las imágenes junto con la tabla)
        [$ini, $fin] = Coordinate::rangeBoundaries($areaImpresion);
        $anchoPx = 0;
        for ($c = $ini[0]; $c <= $fin[0]; $c++) {
            $anchoPx += $this->anchoColumnaPx($sheet, $c);
        }
        $lateral = max(0.3, (($vertical ? 8.5 : 11) - $anchoPx / 96) / 2);

        $m = $sheet->getPageMargins();
        $m->setTop(0.4)->setBottom(0.4)->setLeft($lateral)->setRight($lateral)->setHeader(0.2)->setFooter(0.2);

        $sheet->setShowGridlines(false);
        $sheet->setSelectedCell('A1');
    }

    // ================================================================== Celdas

    /** Marca una casilla con "X" en negrita negra, aplicando el estilo a todo su rango combinado */
    private function marcarX(Worksheet $sheet, string $celda): void
    {
        $this->setValor($sheet, $celda, 'X');
        $rango = $celda;
        foreach ($sheet->getMergeCells() as $merge) {
            if ($sheet->getCell($celda)->isInRange($merge)) {
                $rango = $merge;
                break;
            }
        }
        $estilo = $sheet->getStyle($rango);
        $estilo->getFont()->setBold(true)->getColor()->setARGB('FF000000');
        $estilo->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function setValor(Worksheet $sheet, string $celda, mixed $valor): void
    {
        $texto = trim((string) ($valor ?? ''));
        $sheet->setCellValueExplicit($celda, $texto, DataType::TYPE_STRING);
    }

    /** Texto largo en una celda combinada; aumenta el alto de fila si no cabe */
    private function setTexto(Worksheet $sheet, string $celda, mixed $valor, bool $paraPdf, string $colIni, string $colFin, bool $ajustarAlto = true): void
    {
        $texto = trim((string) ($valor ?? ''));
        if ($texto === '.') {
            $texto = '';
        }
        if ($paraPdf && $texto !== '') {
            // Igual que la HV de cómputo: en PDF los saltos de línea se unen (el convertidor
            // de respaldo cambia la fuente después de cada salto)
            $texto = preg_replace('/\s*\n+\s*/u', ' ', $texto);
        }

        $sheet->setCellValueExplicit($celda, $texto, DataType::TYPE_STRING);
        $al = $sheet->getStyle($celda)->getAlignment();
        $al->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP)->setHorizontal(Alignment::HORIZONTAL_LEFT);

        if ($ajustarAlto && $texto !== '') {
            $fila = (int) preg_replace('/\D/', '', $celda);
            $actual = $sheet->getRowDimension($fila)->getRowHeight();
            $lineas = $this->lineasEstimadas($sheet, $texto, $colIni, $colFin, 11);
            $sheet->getRowDimension($fila)->setRowHeight(max($actual > 0 ? $actual : 15, min(400, $lineas * 15 + 4)));
        }
    }

    private function lineasEstimadas(Worksheet $sheet, string $texto, string $colIni, string $colFin, int $tamFuente): int
    {
        if ($texto === '') {
            return 1;
        }
        $ancho = 0.0;
        $ini = Coordinate::columnIndexFromString($colIni);
        $fin = Coordinate::columnIndexFromString($colFin);
        for ($c = $ini; $c <= $fin; $c++) {
            $w = $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->getWidth();
            $ancho += $w > 0 ? $w : 9.1;
        }
        // Ancho de columna expresado en caracteres de 11pt; escalar según la fuente
        $porLinea = max(10, (int) floor($ancho * 1.1 * (11 / $tamFuente)));

        $lineas = 0;
        foreach (preg_split('/\n/u', $texto) as $parrafo) {
            $lineas += max(1, (int) ceil(mb_strlen($parrafo) / $porLinea));
        }
        return $lineas;
    }

    // ================================================================== Imágenes

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

    private function anchoColumnaPx(Worksheet $sheet, int $columna): int
    {
        $ancho = $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columna))->getWidth();
        if ($ancho < 0) {
            $ancho = 9.109375;
        }
        $mdw = 7;
        return (int) floor(((256 * $ancho + floor(128 / $mdw)) / 256) * $mdw);
    }

    private function altoFila(Worksheet $sheet, int $fila): float
    {
        $alto = $sheet->getRowDimension($fila)->getRowHeight();
        return $alto > 0 ? $alto : 14.4;
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

    /**
     * Ancla la imagen en la celda superior izquierda del rango (la única que los
     * convertidores a PDF dibujan en celdas combinadas) y la desplaza hacia el centro
     * sin salirse de esa celda.
     */
    private function posicionarCentrado(Worksheet $sheet, Drawing $drawing, string $rango, int $w, int $h): void
    {
        [$ini] = Coordinate::rangeBoundaries($rango);
        [$cajaW, $cajaH] = $this->tamanoCaja($sheet, $rango);

        $maxX = max(0, $this->anchoColumnaPx($sheet, $ini[0]) - 2);
        $maxY = max(0, (int) DrawingUnits::pointsToPixels($this->altoFila($sheet, $ini[1])) - 2);

        $ancla = Coordinate::stringFromColumnIndex($ini[0]) . $ini[1];
        // El convertidor de respaldo dibuja la imagen según la alineación de la celda: centrada
        $sheet->getStyle($rango)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $drawing->setCoordinates($ancla);
        $drawing->setOffsetX(min($maxX, max(0, (int) round(($cajaW - $w) / 2))));
        $drawing->setOffsetY(min($maxY, max(0, (int) round(($cajaH - $h) / 2))));
    }

    /**
     * Las imágenes de la plantilla (logo) que están ancladas dentro de una celda combinada
     * se mueven a la celda superior izquierda de esa combinación, conservando tamaño.
     */
    private function reubicarDibujosPlantilla(Worksheet $sheet): void
    {
        foreach ($sheet->getDrawingCollection() as $dibujo) {
            $celda = $dibujo->getCoordinates();
            // El logo de la HV está anclado en la columna A (margen); su recuadro empieza en B
            if (preg_match('/^A(\d+)$/', $celda, $mm)) {
                $celda = 'B' . $mm[1];
                $dibujo->setCoordinates($celda);
            }
            foreach ($sheet->getMergeCells() as $merge) {
                if ($sheet->getCell($celda)->isInRange($merge)) {
                    // Ajustar al recuadro conservando proporción, para que quede centrado
                    [$cajaW, $cajaH] = $this->tamanoCaja($sheet, $merge);
                    $ratio = min(($cajaW * 0.9) / max(1, $dibujo->getWidth()), ($cajaH * 0.85) / max(1, $dibujo->getHeight()));
                    $w = max(1, (int) round($dibujo->getWidth() * $ratio));
                    $h = max(1, (int) round($dibujo->getHeight() * $ratio));
                    $dibujo->setCoordinates2('');
                    $dibujo->setOffsetX2(0);
                    $dibujo->setOffsetY2(0);
                    $dibujo->setResizeProportional(false);
                    $dibujo->setWidth($w);
                    $dibujo->setHeight($h);
                    if ($dibujo instanceof Drawing) {
                        $this->posicionarCentrado($sheet, $dibujo, $merge, $w, $h);
                    }
                    break;
                }
            }
        }
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
                    $tmp = tempnam(sys_get_temp_dir(), 'infra_img_');
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
        $limpia = explode('?', $limpia)[0];

        foreach ([
            storage_path('app/public/' . $limpia),
            public_path('storage/' . $limpia),
            storage_path('app/' . $limpia),
        ] as $candidata) {
            if (file_exists($candidata)) {
                return $candidata;
            }
        }

        if (Storage::disk('public')->exists($limpia)) {
            return Storage::disk('public')->path($limpia);
        }

        // Fotos de equipos que solo existen en producción (mismo respaldo que servirImagen)
        if (str_contains($limpia, 'infraEquipos/')) {
            $archivo = basename($limpia);
            $local = storage_path('app/public/infraEquipos/' . $archivo);
            try {
                $resp = \Illuminate\Support\Facades\Http::timeout(6)
                    ->get('https://nexacoreapi.clinicalhouse.co/public/storage/infraEquipos/' . $archivo);
                if ($resp->successful()) {
                    @mkdir(dirname($local), 0755, true);
                    file_put_contents($local, $resp->body());
                    return $local;
                }
            } catch (\Throwable $e) {
                // sin conexión: se omite la foto
            }
        }

        return null;
    }

    // ================================================================== Utilidades

    private function empresa(): object
    {
        if (!$this->empresaCargada) {
            $this->empresa = DB::table('datos_empresa')->orderBy('id')->first();
            $this->empresaCargada = true;
        }
        return $this->empresa ?? (object) [];
    }

    private function ubicacion(InfraEquipo $equipo): string
    {
        return collect([optional($equipo->area)->nombre, $equipo->ubicacion_especifica])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->implode(' - ');
    }

    private function fecha(mixed $valor): string
    {
        if (!$valor) {
            return '';
        }
        try {
            return ($valor instanceof Carbon ? $valor : Carbon::parse($valor))->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $valor;
        }
    }

    private function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            '' => '',
            'nuevo' => 'Nuevo',
            'usado' => 'Usado',
            'bueno', 'operativo' => 'Bueno / Operativo',
            'regular' => 'Regular',
            'malo' => 'Malo / Deteriorado',
            'en_mantenimiento' => 'En mantenimiento',
            'de_baja', 'baja' => 'Dado de baja',
            default => ucfirst(str_replace('_', ' ', $estado)),
        };
    }

    private function normalizar(?string $v): string
    {
        $v = mb_strtoupper(trim((string) $v));
        return strtr($v, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']);
    }
}
