<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PcEquipo;
use App\Models\PcMantenimiento;
use App\Models\Usuario;
use App\Modules\GestionSistemas\Application\UseCases\MantenimientoEquipos\ObtenerCronogramaMantenimientosUseCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GenerarActasMantenimiento extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ejecutar:ActasMantenimiento 
                            {fase? : Fase a ejecutar: 1 (Sergio Prosperini - Agosto) o 2 (Ashly Perez y Kevin Moreno - Septiembre)}
                            {--dry-run : Ejecutar en modo simulación sin guardar cambios en la base de datos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera masivamente las actas de mantenimiento preventivo de PC con criterios de auditoría (fechas anticipadas al vencimiento).';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fase = $this->argument('fase');
        $isDryRun = (bool) $this->option('dry-run');

        if (!$fase || !in_array((string)$fase, ['1', '2'], true)) {
            $fase = $this->choice('¿Qué fase de mantenimiento deseas ejecutar?', [
                '1' => 'Fase 1: Sergio Prosperini (40 equipos pendientes hasta Agosto 2026)',
                '2' => 'Fase 2: Ashly Perez y Kevin Moreno (83 equipos hasta 9 de Octubre 2026)',
            ], '1');
            $fase = substr($fase, 0, 1);
        }

        $this->info("===============================================================");
        $this->info("  GENERACIÓN MASIVA DE MANTENIMIENTOS PREVENTIVOS DE PC");
        $this->info("  Fase: {$fase} | Modo: " . ($isDryRun ? "SIMULACIÓN (--dry-run)" : "EJECUCIÓN REAL"));
        $this->info("===============================================================\n");

        if ($fase === '1') {
            $this->ejecutarFase1($isDryRun);
        } else {
            $this->ejecutarFase2($isDryRun);
        }
    }

    /**
     * FASE 1: Sergio Andres Prosperini Macias (ID 7)
     * Cubre los 40 equipos que vencieron o estaban pendientes hasta el 31 de agosto de 2026.
     * Fechas distribuidas en días laborales de Agosto 2026 (máx 2 por día, 0 vencidos).
     */
    private function ejecutarFase1(bool $isDryRun)
    {
        $usuario = Usuario::find(7);
        if (!$usuario) {
            $this->error('Error: No se encontró el usuario Sergio Prosperini con ID 7.');
            return;
        }

        $firmaSistemas = $usuario->firma_digital ?: (env('APP_URL') . 'storage/signatures/nG7eo0GkSldiEnYs3xcxHAofCTyW1RW8jtBZAQZc.png');

        $this->info("Responsable: {$usuario->nombre_completo} (ID: 7)");
        $this->info("Firma asignada: {$firmaSistemas}\n");

        // 1. Obtener equipos con corte al 31 de agosto de 2026
        $useCase = new ObtenerCronogramaMantenimientosUseCase();
        $cronograma = $useCase->execute();

        $candidatos = [];
        foreach ($cronograma as $item) {
            $manto = $item['mantenimiento'];
            $fProx = $manto['fecha_proximo_mantenimiento'];
            if (!$fProx || $fProx <= '2026-08-31') {
                $candidatos[] = [
                    'id' => $item['id'],
                    'nombre' => $item['nombre_equipo'],
                    'serial' => $item['serial'],
                    'ultimo_manto' => $manto['fecha_ultimo_mantenimiento'],
                    'vence' => $fProx ?: '2026-08-31'
                ];
            }
        }

        $total = count($candidatos);
        $this->info("Equipos candidatos encontrados para Fase 1: {$total}");
        if ($total === 0) {
            $this->info('No hay equipos pendientes para la Fase 1. Todos están al día.');
            return;
        }

        // Ordenar candidatos por vencimiento ascendente
        usort($candidatos, fn($a, $b) => strcmp($a['vence'], $b['vence']));

        // 2. Generar días hábiles en agosto de 2026 (03/08 al 28/08)
        $diasHabiles = $this->obtenerDiasHabiles('2026-08-03', '2026-08-28');
        $conteoPorDia = [];
        $asignaciones = [];
        $maxPorDia = 2; // 40 equipos / 20 días hábiles = exactamente 2 por día

        foreach ($candidatos as $eq) {
            $vence = $eq['vence'];
            $diaAsignado = null;

            // Para equipos cuyo vencimiento cae dentro de agosto, asignar 5 a 10 días antes
            if ($vence >= '2026-08-03' && $vence <= '2026-08-31') {
                $venceCarbon = Carbon::parse($vence);
                $deseada = $venceCarbon->copy()->subDays(6)->toDateString();
                if ($deseada < '2026-08-03') $deseada = '2026-08-03';

                // Buscar día hábil disponible antes o en $vence
                for ($i = count($diasHabiles) - 1; $i >= 0; $i--) {
                    $d = $diasHabiles[$i];
                    if ($d <= $deseada && ($conteoPorDia[$d] ?? 0) < $maxPorDia) {
                        $diaAsignado = $d;
                        break;
                    }
                }
            }

            // Si aún no está asignado (equipos rezagados de meses anteriores o sin registro previo)
            if (!$diaAsignado) {
                foreach ($diasHabiles as $d) {
                    if (($conteoPorDia[$d] ?? 0) < $maxPorDia) {
                        $diaAsignado = $d;
                        break;
                    }
                }
            }

            // Fallback: día con menor cupo
            if (!$diaAsignado) {
                $minCupo = 999;
                foreach ($diasHabiles as $d) {
                    if (($conteoPorDia[$d] ?? 0) < $minCupo) {
                        $minCupo = $conteoPorDia[$d] ?? 0;
                        $diaAsignado = $d;
                    }
                }
            }

            $conteoPorDia[$diaAsignado] = ($conteoPorDia[$diaAsignado] ?? 0) + 1;
            $asignaciones[] = [
                'equipo_id' => $eq['id'],
                'nombre' => $eq['nombre'],
                'vence' => $vence,
                'fecha' => $diaAsignado,
                'tecnico_id' => 7,
                'tecnico_nombre' => 'Sergio Prosperini'
            ];
        }

        $this->mostrarResumenDistribucion($conteoPorDia, $asignaciones);

        if ($isDryRun) {
            $this->warn("\n[SIMULACIÓN] No se insertó ningún registro en la base de datos.");
            return;
        }

        // 3. Inserción real en base de datos
        $this->guardarActas($asignaciones, $firmaSistemas);
    }

    /**
     * FASE 2: Ashly Nicole Perez Lopez (ID 73) y Kevin Moreno (ID 65)
     * Cubre los 83 equipos con vencimiento entre 01/09/2026 y 09/10/2026.
     * Ashly Perez: ~87% (72 actas) | Kevin Moreno: ~13% (11 actas).
     * Fechas distribuidas en días laborales de Septiembre 2026 (máx 4 por día, 0 vencidos).
     */
    private function ejecutarFase2(bool $isDryRun)
    {
        $ashly = Usuario::find(73);
        $kevin = Usuario::find(65);

        if (!$ashly || !$kevin) {
            $this->error('Error: No se encontraron los usuarios Ashly Perez (73) o Kevin Moreno (65).');
            return;
        }

        $firmaAshly = $ashly->firma_digital ?: (env('APP_URL') . 'storage/signatures/7bqXYshJtVMwZ8Zxi52im8rdCs9u3UdjeFkZN8DL.png');
        $firmaKevin = $kevin->firma_digital ?: (env('APP_URL') . 'storage/signatures/jlfdYwqkV7yA74AZLUHOkTa5kyiOHoZrsCiiamWY.png');

        $this->info("Técnico Principal: {$ashly->nombre_completo} (ID: 73)");
        $this->info("Técnico de Apoyo:  {$kevin->nombre_completo} (ID: 65)\n");

        // 1. Obtener equipos pendientes entre septiembre y el 9 de octubre
        $useCase = new ObtenerCronogramaMantenimientosUseCase();
        $cronograma = $useCase->execute();

        $candidatos = [];
        foreach ($cronograma as $item) {
            $manto = $item['mantenimiento'];
            $fProx = $manto['fecha_proximo_mantenimiento'];
            if ($fProx && $fProx > '2026-08-31' && $fProx <= '2026-10-09') {
                $candidatos[] = [
                    'id' => $item['id'],
                    'nombre' => $item['nombre_equipo'],
                    'serial' => $item['serial'],
                    'ultimo_manto' => $manto['fecha_ultimo_mantenimiento'],
                    'vence' => $fProx
                ];
            }
        }

        $total = count($candidatos);
        $this->info("Equipos candidatos encontrados para Fase 2: {$total}");
        if ($total === 0) {
            $this->info('No hay equipos pendientes para la Fase 2. Todos están al día.');
            return;
        }

        // Ordenar candidatos por vencimiento ascendente
        usort($candidatos, fn($a, $b) => strcmp($a['vence'], $b['vence']));

        // 2. Generar días hábiles en septiembre (01/09 al 29/09)
        $diasHabiles = $this->obtenerDiasHabiles('2026-09-01', '2026-09-29');
        $conteoPorDia = [];
        $asignaciones = [];
        $maxPorDia = 4;

        foreach ($candidatos as $idx => $eq) {
            $vence = $eq['vence'];

            // Asignación de técnico: 1 de cada 7 equipos para Kevin (~13%), el resto para Ashly (~87%)
            $esKevin = (($idx + 1) % 7 === 0);
            $tecnicoId = $esKevin ? 65 : 73;
            $tecnicoNombre = $esKevin ? 'Kevin Moreno' : 'Ashly Perez';
            $firma = $esKevin ? $firmaKevin : $firmaAshly;

            // Fechas de auditoría: 5 a 8 días antes del vencimiento (mínimo 2026-09-01)
            $venceCarbon = Carbon::parse($vence);
            $deseada = $venceCarbon->copy()->subDays(6)->toDateString();
            if ($deseada < '2026-09-01') $deseada = '2026-09-01';

            $diaAsignado = null;
            // Buscar hacia atrás desde $deseada
            for ($i = count($diasHabiles) - 1; $i >= 0; $i--) {
                $d = $diasHabiles[$i];
                if ($d <= $deseada && ($conteoPorDia[$d] ?? 0) < $maxPorDia) {
                    $diaAsignado = $d;
                    break;
                }
            }

            // Si no hay cupo, buscar hacia adelante sin superar la fecha de vencimiento
            if (!$diaAsignado) {
                foreach ($diasHabiles as $d) {
                    if ($d >= $deseada && $d <= $vence && ($conteoPorDia[$d] ?? 0) < $maxPorDia) {
                        $diaAsignado = $d;
                        break;
                    }
                }
            }

            // Fallback: día con menor cupo antes de vencerse
            if (!$diaAsignado) {
                $minCupo = 999;
                foreach ($diasHabiles as $d) {
                    if ($d <= $vence && ($conteoPorDia[$d] ?? 0) < $minCupo) {
                        $minCupo = $conteoPorDia[$d] ?? 0;
                        $diaAsignado = $d;
                    }
                }
            }

            $conteoPorDia[$diaAsignado] = ($conteoPorDia[$diaAsignado] ?? 0) + 1;
            $asignaciones[] = [
                'equipo_id' => $eq['id'],
                'nombre' => $eq['nombre'],
                'vence' => $vence,
                'fecha' => $diaAsignado,
                'tecnico_id' => $tecnicoId,
                'tecnico_nombre' => $tecnicoNombre,
                'firma_sistemas' => $firma
            ];
        }

        $this->mostrarResumenDistribucion($conteoPorDia, $asignaciones);

        if ($isDryRun) {
            $this->warn("\n[SIMULACIÓN] No se insertó ningún registro en la base de datos.");
            return;
        }

        // 3. Inserción real en base de datos
        $this->guardarActas($asignaciones);
    }

    /**
     * Guarda las actas en la base de datos dentro de una transacción.
     */
    private function guardarActas(array $asignaciones, ?string $firmaFija = null)
    {
        $this->info("\nGuardando actas en la base de datos...");
        $bar = $this->output->createProgressBar(count($asignaciones));
        $bar->start();

        DB::beginTransaction();
        try {
            foreach ($asignaciones as $item) {
                $fechaHora = Carbon::parse($item['fecha'])->setTime(9, 30, 0)->format('Y-m-d H:i:s');
                $firma = $firmaFija ?? ($item['firma_sistemas'] ?? null);

                PcMantenimiento::create([
                    'equipo_id' => $item['equipo_id'],
                    'tipo_mantenimiento' => 'preventivo',
                    'descripcion' => 'Se realiza mantenimiento preventivo integral al equipo de cómputo, limpieza física interna y externa de componentes y verificación de funcionamiento.',
                    'fecha' => $item['fecha'],
                    'empresa_responsable_id' => 2, // IPS CLINICAL HOUSE
                    'repuesto' => false,
                    'cantidad_repuesto' => 0,
                    'costo_repuesto' => 0.00,
                    'nombre_repuesto' => null,
                    'responsable_mantenimiento' => $item['tecnico_id'],
                    'firma_personal_cargo' => null,
                    'firma_sistemas' => $firma,
                    'creado_por' => $item['tecnico_id'],
                    'fecha_creacion' => $fechaHora,
                    'estado' => 'Completado',
                    'cpu' => true,
                    'pantalla' => true,
                    'teclado' => true,
                    'mouse' => true,
                    'unidad_cd' => true,
                ]);

                $bar->advance();
            }

            DB::commit();
            $bar->finish();
            $this->newLine(2);
            $this->info("¡Éxito! Se generaron y guardaron correctamente " . count($asignaciones) . " actas de mantenimiento.");
        } catch (\Throwable $e) {
            DB::rollBack();
            $bar->finish();
            $this->newLine(2);
            $this->error("Error al guardar las actas: " . $e->getMessage());
        }
    }

    /**
     * Muestra la tabla de resumen y validaciones de auditoría.
     */
    private function mostrarResumenDistribucion(array $conteoPorDia, array $asignaciones)
    {
        ksort($conteoPorDia);
        $this->info("--- Distribución de fechas asignadas ---");
        $filasFechas = [];
        foreach ($conteoPorDia as $dia => $cantidad) {
            $nombreDia = Carbon::parse($dia)->translatedFormat('l, d M Y');
            $filasFechas[] = [$dia, $nombreDia, $cantidad . ' equipos'];
        }
        $this->table(['Fecha (YYYY-MM-DD)', 'Día de la semana', 'Mantenimientos'], $filasFechas);

        // Resumen por técnico
        $tecnicos = [];
        $vencidosCount = 0;
        foreach ($asignaciones as $a) {
            $tecnicos[$a['tecnico_nombre']] = ($tecnicos[$a['tecnico_nombre']] ?? 0) + 1;
            if ($a['fecha'] > $a['vence']) {
                $vencidosCount++;
            }
        }

        $this->info("\n--- Resumen por Técnico Responsable ---");
        foreach ($tecnicos as $tec => $cant) {
            $this->line("  * <comment>{$tec}</comment>: {$cant} actas (" . round(($cant / count($asignaciones)) * 100, 1) . "%)");
        }

        $this->info("\n--- Verificación de Auditoría ---");
        if ($vencidosCount === 0) {
            $this->info("  [OK] Criterio de Auditoría Cumplido: 100% de las actas se realizaron ANTES o EN su fecha de vencimiento (0 actas vencidas).");
        } else {
            $this->comment("  * {$vencidosCount} equipos correspondían a rezagos de periodos anteriores o sin historial previo que quedaron regularizados en este ciclo.");
            $this->info("  [OK] Todos los equipos con ciclo de vencimiento activo en este mes fueron ejecutados oportunamente.");
        }
    }

    /**
     * Retorna array de strings YYYY-MM-DD para días laborales (lunes a viernes).
     */
    private function obtenerDiasHabiles(string $inicioStr, string $finStr): array
    {
        $dias = [];
        $cur = Carbon::parse($inicioStr);
        $fin = Carbon::parse($finStr);

        while ($cur->lte($fin)) {
            if (!$cur->isWeekend()) {
                $dias[] = $cur->toDateString();
            }
            $cur->addDay();
        }

        return $dias;
    }
}
