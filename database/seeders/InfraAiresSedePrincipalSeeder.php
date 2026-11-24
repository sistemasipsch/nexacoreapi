<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\InfraEquipo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Carga los 26 aires acondicionados de la sede principal tal cual el archivo
 * "inventario_infraestructura_principal.xlsx" en Gestión de Infraestructura (equipos y hojas de vida).
 *
 * Se puede ejecutar varias veces: si el aire ya existe lo actualiza, no lo duplica.
 *   php artisan db:seed --class=InfraAiresSedePrincipalSeeder
 *
 * Los nombres del Excel se convierten en los IDs del sistema:
 *  - Sede "IPS CLINICAL HOUSE"            → SEDE PRINCIPAL
 *  - Responsable "TECNICO MANTENIMIENTOS" → técnico de mantenimiento (JHON MONTEALEGRE, tabla personal)
 *  - Área → área de la sede principal con el mismo nombre; si no existe se crea tal cual
 */
class InfraAiresSedePrincipalSeeder extends Seeder
{
    private const SEDE_EXCEL = 'IPS CLINICAL HOUSE';
    private const SEDE_SISTEMA = 'SEDE PRINCIPAL';

    /** Técnico de mantenimiento registrado en personal (cargo AREA MANTENIMIENTO). */
    private const TECNICO_CEDULA = '93398061';
    private const TECNICO_NOMBRE = 'MONTEALEGRE';

    /** Nombres del Excel que corresponden a un área ya creada con otro nombre. */
    private const ALIAS_AREAS = [
        'SALA DE PROCEDIMIENTOS MENORES' => 'Procedimientos Menores',
    ];

    private const COLUMNAS = [
        'nombre_equipo', 'tipo_equipo', 'marca', 'modelo', 'serial', 'activo_fijo', 'capacidad', 'voltaje',
        'amperaje', 'frecuencia', 'sede', 'area', 'ubicacion_especifica', 'responsable', 'forma_adquisicion',
        'fecha_compra', 'garantia_meses', 'fecha_instalacion', 'vida_util_anos', 'frecuencia_mantenimiento',
        'estado_equipo', 'observaciones',
    ];

    /** Filas del Excel en el mismo orden (id 1 a 26), sin modificar. */
    private function filas(): array
    {
        return [
            ['AIRE ACONDICIONADO', 'MINI SPLIT', 'MABE', 'MMI12CABWCCC8', 'ST22091621GCC0469', 'MAQ0010', '3517 W', '115 V', '7.9', '60HZ', 'IPS CLINICAL HOUSE', 'CALIDAD', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'SPLIT', 'OLIMPO', 'OLACI24-220MIC', '540K953120338240130194', 'MAQ0258', '18000 BTU', '220-240 V', '15A', '60HZ', 'IPS CLINICAL HOUSE', 'COMUNICACIONES', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'INVERTER', 'LG', 'VM242CE NC2', '402HAXC00274', 'MAQ0013', '22000 BTU', '220 V', '9,10 A', '60HZ', 'IPS CLINICAL HOUSE', 'COMPRAS', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'MINI SPLIT', 'PANASONIC', 'CS-YS24YKV-6', '540G086160926060130023', 'MAQ0014', '5707 W', '220 V', '7,9', '60HZ', 'IPS CLINICAL HOUSE', 'GESTION FINANCIERA', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'MINI SPLIT', 'MABE', 'MMI12CABWCCCI8', 'ST240622721GCC0048', 'MAQ0259', '3517 W', '115V', '7,9', '60HZ', 'IPS CLINICAL HOUSE', 'SISTEMAS RACK', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'MINI SPLIT', 'MCQUAY', 'MQMI-18012-CCU216A', '540P177690345060130027', 'MAQ0260', '12000 BTU', '220-230 V', '9,5 A', '60HZ', 'IPS CLINICAL HOUSE', 'TALENTO HUMANO', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'SPLIT', 'SAMSUNG', 'AR12TVFZAWK', '0GU3PDBR900207F', 'MAQ0015', '12000 BTU', '220 V', '4,8 A', '60HZ', 'IPS CLINICAL HOUSE', 'GERENCIA', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'MINI SPLIT INVERTER', 'MABE', 'MMI18CDBWCCCI8', 'T23052221GCG0290', 'MAQ0028', '18000 BTU', '220-230 V', '7.6', '60HZ', 'IPS CLINICAL HOUSE', 'BAÑO PASILLO DISCAPACITADOS', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'MINI SPLIT INVERTER', 'OLIMPO', 'OLACI24-220MIC', '540K953120338240130241', 'MAQ0032', '24000 BTU', '220-240 V', '9,5 A', '60HZ', 'IPS CLINICAL HOUSE', 'FARMACIA', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'MINI SPLIT INVERTER', 'OLIMPO', 'TWC12QC-D3DNA5F', '3EA00001303', 'MAQ0009', '12000 BTU', '208-230 V', '3.9', '60HZ', 'IPS CLINICAL HOUSE', 'SALA DE PROCEDIMIENTOS MENORES', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'MINI SPLIT INVERTER', 'MIDEA', 'MSAFC-17CRDN1-NQ0W', '54122000DZY36110170042', 'MAQ0024', '18000 BTU', '220-230 A', '15,91 A', '60HZ', 'IPS CLINICAL HOUSE', 'SALA DE ESPERA .1', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'MINI SPLIT', 'OLIMPO', 'OLACI09-110GPC', '3J58130000253', 'MAQ0067', '9000 BTU', '115 V', '7,9', '60HZ', 'IPS CLINICAL HOUSE', 'AUDIOMETRIA', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'MINI SPLIT INVERTER', 'OLIMPO', 'OLACI24-220MIC', '540K953120338240130177', 'MAQ0031', '24000 BTU', '220-240 V', '7,9', '60HZ', 'IPS CLINICAL HOUSE', 'RECEPCION', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'MINI SPLIT', 'MIDEA', 'MSAFB-12CRDN1-NQ0W', '54122000DZU34150130321', 'MAQ0023', '12000 BTU', '220-230 V', '7,9', '60HZ', 'IPS CLINICAL HOUSE', 'CONSULTORIO N. 8', 'RECEPCION (2)', 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', 'PENDIENTE'],
            ['AIRE ACONDICIONADO', 'AIRE ACONDICIONADO DE TECHO / CEILING', 'SAMSUNG', 'AC036MNCDKH', '0SSHPAOK900004V', 'MAQ0026', '36000 BTU', '220-240 V', '14,6 A', '60HZ', 'IPS CLINICAL HOUSE', 'RECEPCION PISO TECHO', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'MINI SPLIT', 'DAIKIN', 'FTKS18JL216A', '3K90250000985', 'MAQ0261', '18000 BTU', '220 V', '9', '60HZ', 'IPS CLINICAL HOUSE', 'VACUNACION', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2026', NULL, '2026-06-23', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'SPLIT', 'McQuay', 'MQMI-18036-CCU216A', '540P3447003460600130095', 'MAQ0065', '36000 BTU', '208-230 V', '16,0 A', '60HZ', 'IPS CLINICAL HOUSE', 'PLAN CANGURO', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2026', NULL, '2026-06-23', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'MINI SPLIT', 'MABE', 'MMI12CDBWCCCI8', 'ST21090721GCE0224', 'MAQ0022', '12000 BTU', '220-230 V', '4,8 A', '60HZ', 'IPS CLINICAL HOUSE', 'ADMINISTRACION DE MEDICAMENTOS', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'SPLIT', 'LG', 'VM182CE NC3', 'BSN0-2C4W3', 'MAQ0002', '17000 BTU', '220 V', '6,8 A', '60HZ', 'IPS CLINICAL HOUSE', 'CONSULTORIO N. 1', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'MINI SPLIT', 'MIDEA', 'MSAFB-12CRDN1-NQ0W', '54122000DZU36270131078', 'MAQ0029', '12000 BTU', '220-230 V', '9,5 A', '60HZ', 'IPS CLINICAL HOUSE', 'CONSULTORIO N. 2', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'MINI SPLIT', 'MABE', 'MMI12CDBWCCCHIII8', 'ST20101949GCE1351', 'MAQ0003', '12000 BTU', '220 V', '7,9', '60HZ', 'IPS CLINICAL HOUSE', 'CONSULTORIO N. 3', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'SPLIT', 'LG', 'VM122CE NB3', '402HAMG00291', 'MAQ0005', '11800 BTU', '220 V', '5,8 A', '60HZ', 'IPS CLINICAL HOUSE', 'CONSULTORIO N. 4', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'SPLIT INVERTER', 'OLIMPO', 'OLACI12-220MA21', '340D27873011A140170562', 'MAQ0007', '12000 BTU', '220-240 V', '4.9', '60HZ', 'IPS CLINICAL HOUSE', 'CONSULTORIO N. 5', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'SPLIT', 'OLIMPO', 'OLACI12-220MA21', '340D27873011A140170659', 'MAQ0004', '12000 BTU', '220-240 V', '9,5 A', '60HZ', 'IPS CLINICAL HOUSE', 'CONSULTORIO N. 6', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'SPLIT INVERTER', 'OLIMPO', 'OLACI12-220MA21', '340D27873011A140170562', 'MAQ0007', '12000 BTU', '220-240 V', '4.9', '60HZ', 'IPS CLINICAL HOUSE', 'CONSULTORIO N. 7', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
            ['AIRE ACONDICIONADO', 'MINI SPLIT', 'MIDEA', 'MSAFB-12CRDN1-NQ0W', '54122000DZU34150130321', 'MAQ0023', '12000 BTU', '220-230 V', '7,9', '60HZ', 'IPS CLINICAL HOUSE', 'CONSULTORIO N. 8', NULL, 'TECNICO MANTENIMIENTOS', 'COMPRA DIRECTA', '2014', NULL, '2014-09-01', '12', 'MENSUAL', 'NUEVO', NULL],
        ];
    }

    public function run(): void
    {
        DB::transaction(function () {
            $sedeId = $this->sedeId();
            $tecnicoId = $this->tecnicoId();

            $vistos = ['serial' => [], 'activo_fijo' => []];
            $creados = 0;
            $actualizados = 0;

            foreach ($this->filas() as $n => $valores) {
                $fila = array_combine(self::COLUMNAS, $valores);
                $numero = $n + 1;

                if (mb_strtoupper((string) $fila['sede']) !== self::SEDE_EXCEL) {
                    throw new RuntimeException("Fila {$numero}: sede desconocida \"{$fila['sede']}\".");
                }

                $datos = [
                    'nombre_equipo' => $fila['nombre_equipo'],
                    'tipo_equipo' => $fila['tipo_equipo'],
                    'marca' => $fila['marca'],
                    'modelo' => $fila['modelo'],
                    'serial' => $fila['serial'],
                    'activo_fijo' => $fila['activo_fijo'],
                    'capacidad' => $fila['capacidad'],
                    'voltaje' => $fila['voltaje'],
                    'amperaje' => $fila['amperaje'],
                    'frecuencia' => $fila['frecuencia'],
                    'sede_id' => $sedeId,
                    'area_id' => $this->areaId($fila['area'], $sedeId),
                    'ubicacion_especifica' => $fila['ubicacion_especifica'],
                    'responsable_id' => $tecnicoId,
                    // Los valores permitidos en la base van en minúscula
                    'forma_adquisicion' => mb_strtolower($fila['forma_adquisicion']),
                    // El Excel solo trae el año de compra: se guarda el 1 de enero de ese año
                    'fecha_compra' => $fila['fecha_compra'] ? $fila['fecha_compra'] . '-01-01' : null,
                    'garantia_meses' => $fila['garantia_meses'] !== null ? (int) $fila['garantia_meses'] : null,
                    'fecha_instalacion' => $fila['fecha_instalacion'],
                    'vida_util_anos' => $fila['vida_util_anos'] !== null ? (int) $fila['vida_util_anos'] : null,
                    // Mismo valor que usa el formulario del módulo ("Mensual")
                    'frecuencia_mantenimiento' => mb_convert_case(mb_strtolower($fila['frecuencia_mantenimiento']), MB_CASE_TITLE),
                    'estado_equipo' => mb_strtolower($fila['estado_equipo']),
                    'observaciones' => $fila['observaciones'],
                ];

                // La base no permite repetir serial ni activo fijo. El Excel trae dos aires repetidos
                // (MAQ0007 y MAQ0023): el segundo se carga sin esos datos y con una observación.
                $repetidos = [];
                foreach (['serial', 'activo_fijo'] as $campo) {
                    $valor = $datos[$campo];
                    if ($valor === null) {
                        continue;
                    }
                    if (isset($vistos[$campo][$valor])) {
                        $repetidos[] = ($campo === 'serial' ? 'serial ' : 'activo fijo ') . $valor;
                        $datos[$campo] = null;
                    } else {
                        $vistos[$campo][$valor] = true;
                    }
                }
                if ($repetidos) {
                    $nota = 'En el inventario este aire tiene ' . implode(' y ', $repetidos)
                        . ', iguales a los de otro aire: verificar la placa del equipo.';
                    $datos['observaciones'] = trim(($datos['observaciones'] ? $datos['observaciones'] . '. ' : '') . $nota);
                }

                $existente = $this->buscarExistente($datos);
                if ($existente) {
                    $existente->update($datos);
                    $actualizados++;
                } else {
                    InfraEquipo::create($datos);
                    $creados++;
                }
            }

            $this->command?->info("Aires sede principal: {$creados} creados, {$actualizados} actualizados.");
        });
    }

    private function buscarExistente(array $datos): ?InfraEquipo
    {
        if ($datos['activo_fijo']) {
            return InfraEquipo::where('activo_fijo', $datos['activo_fijo'])->first();
        }
        if ($datos['serial']) {
            return InfraEquipo::where('serial', $datos['serial'])->first();
        }
        // Aire cargado sin serial ni activo (repetido): se identifica por modelo y área
        return InfraEquipo::whereNull('activo_fijo')
            ->whereNull('serial')
            ->where('modelo', $datos['modelo'])
            ->where('area_id', $datos['area_id'])
            ->where('sede_id', $datos['sede_id'])
            ->first();
    }

    private function sedeId(): int
    {
        $id = DB::table('sedes')->where('nombre', self::SEDE_SISTEMA)->value('id');
        if (!$id) {
            throw new RuntimeException('No existe la sede "' . self::SEDE_SISTEMA . '".');
        }
        return (int) $id;
    }

    private function tecnicoId(): int
    {
        $id = DB::table('personal')->where('cedula', self::TECNICO_CEDULA)->value('id')
            ?? DB::table('personal')->where('nombre', 'like', '%' . self::TECNICO_NOMBRE . '%')->value('id');
        if (!$id) {
            throw new RuntimeException('No se encontró al técnico de mantenimiento en la tabla personal.');
        }
        return (int) $id;
    }

    /** Busca el área en la sede ignorando mayúsculas, tildes y "N." (CONSULTORIO N. 8 = Consultorio 8). */
    private function areaId(?string $nombreExcel, int $sedeId): ?int
    {
        if (!$nombreExcel) {
            return null;
        }

        $buscado = $this->normalizar(self::ALIAS_AREAS[$nombreExcel] ?? $nombreExcel);

        $area = Area::where('sede_id', $sedeId)->orderBy('id')->get()
            ->first(fn ($a) => $this->normalizar($a->nombre) === $buscado);

        if (!$area) {
            $area = Area::create(['nombre' => $nombreExcel, 'sede_id' => $sedeId]);
        }

        return (int) $area->id;
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
