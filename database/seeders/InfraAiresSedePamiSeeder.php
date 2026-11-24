<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\InfraEquipo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Carga los aires acondicionados de la SEDE PAMI desde el CSV
 * "inventario_infraestructura_aires_sede_pami.csv" (separado por ";").
 *
 * 1. Copiar el CSV a: database/seeders/data/inventario_infraestructura_aires_sede_pami.csv
 * 2. Simular (no guarda nada, solo muestra lo que haría):
 *      INFRA_SIMULAR=1 php artisan db:seed --class=InfraAiresSedePamiSeeder
 * 3. Cargar:
 *      php artisan db:seed --class=InfraAiresSedePamiSeeder
 *
 * Se puede ejecutar varias veces: si el aire ya existe (mismo activo fijo o serial) lo actualiza.
 *
 * Cómo se acomodan los datos del CSV:
 *  - Sede "IPS CLINICAL HOUSE"   → SEDE PAMI (el archivo es de esa sede)
 *  - Responsable (nombre)        → id en la tabla personal; si no está registrado queda vacío
 *  - Área (nombre)               → área de la SEDE PAMI con el mismo nombre; si no existe se crea
 *  - "PENDIENTE" en serial, activo fijo, área o ubicación → vacío
 *  - fecha_compra "2024"         → 2024-01-01
 *  - fecha_instalacion "MARZO"   → 1 de marzo del año de compra
 *  - garantia_meses "SI"         → vacío, y se anota "Con garantía" en observaciones
 *  - vida_util_anos "8 - 12 AÑOS" → 12 (el mayor valor del rango)
 *  - forma_adquisicion / estado vacíos → "compra directa" / "nuevo" (valores por defecto de la tabla)
 *  - Traslado: la sede de origen va a traslado_sede_id; el área de origen se anota en observaciones
 *  - Paréntesis sin cerrar ("MINI SPLIT (Inverter") se cierran
 */
class InfraAiresSedePamiSeeder extends Seeder
{
    private const ARCHIVO = 'seeders/data/inventario_infraestructura_aires_sede_pami.csv';
    private const SEDE_SISTEMA = 'SEDE PAMI';

    /** Nombres de sede que aparecen en el CSV y la sede del sistema a la que corresponden. */
    private const SEDES_CSV = [
        'IPS CLINICAL HOUSE' => 'SEDE PAMI',
        'SEDE PAMI' => 'SEDE PAMI',
        'SEDE PRINCIPAL' => 'SEDE PRINCIPAL',
    ];

    /** Nombres del CSV que corresponden a un área ya creada con otro nombre. */
    private const ALIAS_AREAS = [
        'CONSULTORIO 8 (ODONTOLOGIA)' => 'Consultorio 8',
        'RACK' => 'Rack de Comunicaciones',
        'SALA DE PROCEDIMIENTOS' => 'Procedimientos',
    ];

    private const MESES = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7,
        'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
    ];

    private array $avisos = [];

    public function run(): void
    {
        $filas = $this->leerCsv();
        $simular = (bool) env('INFRA_SIMULAR', false);

        DB::beginTransaction();
        try {
            $sedeId = $this->sedeId(self::SEDE_SISTEMA);
            $creados = 0;
            $actualizados = 0;

            foreach ($filas as $numero => $fila) {
                $datos = $this->acomodarFila($fila, $numero, $sedeId);

                $existente = $this->buscarExistente($datos);
                if ($existente) {
                    $existente->update($datos);
                    $actualizados++;
                    $accion = 'actualiza';
                } else {
                    InfraEquipo::create($datos);
                    $creados++;
                    $accion = 'crea';
                }

                $this->command?->line(sprintf(
                    '  Fila %2d: %-9s %-10s %-24s área #%s',
                    $numero, $accion, $datos['activo_fijo'] ?? '(sin activo)', $datos['serial'] ?? '(sin serial)', $datos['area_id'] ?? '-'
                ));
            }

            foreach (array_unique($this->avisos) as $aviso) {
                $this->command?->warn($aviso);
            }

            if ($simular) {
                DB::rollBack();
                $this->command?->info("SIMULACIÓN: se crearían {$creados} y se actualizarían {$actualizados} aires. No se guardó nada.");
                return;
            }

            DB::commit();
            $this->command?->info("Aires SEDE PAMI: {$creados} creados, {$actualizados} actualizados.");
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /** @return array<int, array<string, ?string>> filas del CSV indexadas por número de fila (1 = primer aire) */
    private function leerCsv(): array
    {
        $ruta = env('INFRA_CSV') ?: database_path(self::ARCHIVO);
        if (!is_file($ruta)) {
            throw new RuntimeException("No se encontró el CSV en {$ruta}");
        }

        $h = fopen($ruta, 'r');
        $encabezado = fgetcsv($h, 0, ';');
        $encabezado[0] = preg_replace('/^\xEF\xBB\xBF/', '', $encabezado[0]); // BOM de Excel
        $encabezado = array_map('trim', $encabezado);

        $filas = [];
        $n = 0;
        while (($valores = fgetcsv($h, 0, ';')) !== false) {
            if (count(array_filter($valores, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // línea vacía
            }
            $n++;
            $valores = array_pad($valores, count($encabezado), null);
            $fila = array_combine($encabezado, array_slice($valores, 0, count($encabezado)));
            $filas[$n] = array_map(fn ($v) => $this->limpiar($v), $fila);
        }
        fclose($h);

        return $filas;
    }

    private function acomodarFila(array $f, int $numero, int $sedeId): array
    {
        $sedeCsv = mb_strtoupper((string) $f['sede_id']);
        if ($sedeCsv !== '' && (self::SEDES_CSV[$sedeCsv] ?? null) !== self::SEDE_SISTEMA) {
            throw new RuntimeException("Fila {$numero}: sede \"{$f['sede_id']}\" no corresponde a " . self::SEDE_SISTEMA . '.');
        }

        $observaciones = [];
        if ($f['observaciones']) {
            $observaciones[] = $f['observaciones'];
        }

        // Fechas: el CSV trae solo el año de compra y, a veces, el mes de instalación en letras
        $anioCompra = preg_match('/^\d{4}$/', (string) $f['fecha_compra']) ? (int) $f['fecha_compra'] : null;
        $fechaCompra = $anioCompra ? "{$anioCompra}-01-01" : $this->fecha($f['fecha_compra']);
        $fechaInstalacion = $this->fecha($f['fecha_instalacion']);
        $mes = self::MESES[mb_strtolower((string) $f['fecha_instalacion'])] ?? null;
        if (!$fechaInstalacion && $mes && $anioCompra) {
            $fechaInstalacion = sprintf('%04d-%02d-01', $anioCompra, $mes);
        } elseif (!$fechaInstalacion && $f['fecha_instalacion']) {
            $observaciones[] = "Instalación: {$f['fecha_instalacion']}";
        }

        // Garantía: número de meses; "SI" u otro texto se anota
        $garantia = null;
        if ($f['garantia_meses'] !== null) {
            if (ctype_digit($f['garantia_meses'])) {
                $garantia = (int) $f['garantia_meses'];
            } else {
                $observaciones[] = mb_strtoupper($f['garantia_meses']) === 'SI' ? 'Con garantía' : "Garantía: {$f['garantia_meses']}";
            }
        }

        // Traslado: sede de origen → traslado_sede_id; el área de origen se anota
        $trasladoSedeId = null;
        if ($f['traslado_dependencia_id']) {
            $nombreSede = self::SEDES_CSV[mb_strtoupper($f['traslado_dependencia_id'])] ?? $f['traslado_dependencia_id'];
            $trasladoSedeId = DB::table('sedes')->where('nombre', $nombreSede)->value('id');
        }
        if ($f['traslado_dependencia_id'] || $f['traslado_area_id'] || $f['fecha_traslado']) {
            $origen = trim(implode(' - ', array_filter([$f['traslado_dependencia_id'], $f['traslado_area_id']])));
            $observaciones[] = 'Trasladado' . ($origen ? " desde {$origen}" : '') . ($f['fecha_traslado'] ? " el {$f['fecha_traslado']}" : '');
        }

        return [
            'nombre_equipo' => $f['nombre_equipo'] ?? 'AIRE ACONDICIONADO',
            'tipo_equipo' => $f['tipo_equipo'] ?? 'MINI SPLIT',
            'marca' => $f['marca'],
            'modelo' => $f['modelo'],
            'serial' => $this->sinPendiente($f['serial']),
            'activo_fijo' => $this->sinPendiente($f['activo_fijo']),
            'imagen_url' => $f['imagen_url'],
            'capacidad' => $f['capacidad'],
            'voltaje' => $f['voltaje'],
            'amperaje' => $f['amperaje'],
            'frecuencia' => $f['frecuencia'],
            'sede_id' => $sedeId,
            'area_id' => $this->areaId($this->sinPendiente($f['area_id']), $sedeId),
            'ubicacion_especifica' => $this->sinPendiente($f['ubicacion_especifica']),
            'responsable_id' => $this->personalId($f['responsable_id']),
            'forma_adquisicion' => $this->formaAdquisicion($f['forma_adquisicion']),
            'proveedor_id' => null,
            'fecha_compra' => $fechaCompra,
            'garantia_meses' => $garantia,
            'fecha_instalacion' => $fechaInstalacion,
            'vida_util_anos' => $this->vidaUtil($f['vida_util_anos']),
            // Mismo valor que usa el formulario del módulo ("Mensual")
            'frecuencia_mantenimiento' => $f['frecuencia_mantenimiento']
                ? mb_convert_case(mb_strtolower($f['frecuencia_mantenimiento']), MB_CASE_TITLE)
                : 'Trimestral',
            'estado_equipo' => $this->estado($f['estado_equipo']),
            'fecha_traslado' => $this->fecha($f['fecha_traslado']),
            'traslado_sede_id' => $trasladoSedeId,
            'observaciones' => $observaciones ? implode('. ', $observaciones) : null,
        ];
    }

    private function buscarExistente(array $datos): ?InfraEquipo
    {
        if ($datos['activo_fijo']) {
            if ($e = InfraEquipo::where('activo_fijo', $datos['activo_fijo'])->first()) {
                return $e;
            }
        }
        if ($datos['serial']) {
            if ($e = InfraEquipo::where('serial', $datos['serial'])->first()) {
                return $e;
            }
        }
        if ($datos['activo_fijo'] || $datos['serial']) {
            return null;
        }
        // Aire sin serial ni activo: se identifica por marca, modelo y área en la sede
        return InfraEquipo::whereNull('activo_fijo')
            ->whereNull('serial')
            ->where('sede_id', $datos['sede_id'])
            ->where(fn ($q) => $datos['area_id'] ? $q->where('area_id', $datos['area_id']) : $q->whereNull('area_id'))
            ->where(fn ($q) => $datos['modelo'] ? $q->where('modelo', $datos['modelo']) : $q->whereNull('modelo'))
            ->where(fn ($q) => $datos['marca'] ? $q->where('marca', $datos['marca']) : $q->whereNull('marca'))
            ->first();
    }

    private function sedeId(string $nombre): int
    {
        $id = DB::table('sedes')->where('nombre', $nombre)->value('id');
        if (!$id) {
            throw new RuntimeException("No existe la sede \"{$nombre}\".");
        }
        return (int) $id;
    }

    /** Busca a la persona en la tabla personal (allí el nombre va con apellidos primero). */
    private function personalId(?string $nombre): ?int
    {
        if (!$nombre) {
            return null;
        }
        $palabras = array_filter(explode(' ', $this->normalizar($nombre)), fn ($p) => mb_strlen($p) > 2);

        $candidatos = DB::table('personal')
            ->where(function ($q) use ($palabras) {
                foreach ($palabras as $p) {
                    $q->orWhere('nombre', 'like', "%{$p}%");
                }
            })
            ->get(['id', 'nombre']);

        foreach ($candidatos as $c) {
            $suyas = explode(' ', $this->normalizar($c->nombre));
            if (!array_diff($palabras, $suyas)) {
                return (int) $c->id; // todas las palabras del CSV están en su nombre
            }
        }

        $this->avisos[] = "Responsable \"{$nombre}\" no está registrado en Personal: los aires quedan sin responsable. "
            . 'Regístrelo en Personal y vuelva a ejecutar el seeder para asignarlo.';
        return null;
    }

    /** Busca el área en la sede ignorando mayúsculas, tildes y "N."; si no existe la crea. */
    private function areaId(?string $nombre, int $sedeId): ?int
    {
        if (!$nombre) {
            return null;
        }

        $buscado = $this->normalizar(self::ALIAS_AREAS[mb_strtoupper($nombre)] ?? $nombre);

        $area = Area::where('sede_id', $sedeId)->orderBy('id')->get()
            ->first(fn ($a) => $this->normalizar($a->nombre) === $buscado);

        if (!$area) {
            $area = Area::create(['nombre' => $nombre, 'sede_id' => $sedeId]);
            $this->avisos[] = "Se creó el área \"{$nombre}\" en " . self::SEDE_SISTEMA . '.';
        }

        return (int) $area->id;
    }

    private function formaAdquisicion(?string $v): string
    {
        $v = $this->normalizar((string) $v);
        return in_array($v, ['compra directa', 'alquiler', 'donacion', 'comodato'], true) ? $v : 'compra directa';
    }

    private function estado(?string $v): string
    {
        $v = str_replace(' ', '_', $this->normalizar((string) $v));
        return in_array($v, ['nuevo', 'usado', 'bueno', 'regular', 'malo', 'de_baja'], true) ? $v : 'nuevo';
    }

    /** "12" → 12, "8 - 12 AÑOS" → 12 */
    private function vidaUtil(?string $v): ?int
    {
        if (!$v || !preg_match_all('/\d+/', $v, $m)) {
            return null;
        }
        return (int) max($m[0]);
    }

    /** Acepta 2024-12-01, 01/12/2024 o 2024; lo demás queda vacío. */
    private function fecha(?string $v): ?string
    {
        if (!$v) {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $v, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (preg_match('/^\d{4}$/', $v)) {
            return "{$v}-01-01";
        }
        return null;
    }

    private function sinPendiente(?string $v): ?string
    {
        return ($v === null || str_starts_with(mb_strtoupper($v), 'PENDIENTE')) ? null : $v;
    }

    /** Quita espacios sobrantes, deja vacíos como null y cierra paréntesis abiertos. */
    private function limpiar(?string $v): ?string
    {
        $v = trim(preg_replace('/\s+/u', ' ', (string) $v));
        if ($v === '') {
            return null;
        }
        $faltan = substr_count($v, '(') - substr_count($v, ')');
        return $faltan > 0 ? $v . str_repeat(')', $faltan) : $v;
    }

    private function normalizar(string $texto): string
    {
        $t = mb_strtolower(trim($texto));
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $t = preg_replace('/\bn[º°o]?\.\s*/u', '', $t);   // "consultorio n. 8" → "consultorio 8"
        $t = preg_replace('/[^a-z0-9]+/u', ' ', $t);
        return trim(preg_replace('/\s+/', ' ', $t));
    }
}
