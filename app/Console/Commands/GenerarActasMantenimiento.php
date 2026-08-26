<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PcEquipo;
use App\Models\PcMantenimiento;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;



class GenerarActasMantenimiento extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ejecutar:ActasMantenimiento';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera las actas de mantenimiento para los equipos que requieren mantenimiento.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // mensaje de inicio
        $this->info('Iniciando la generación de actas de mantenimiento...');

        // 1. obtener los equipos que no tiene mantenimiento \
        $equipos = PcEquipo::doesntHave('mantenimientos')->get();
        $totalEquipos = $equipos->count();

        $this->info("Se encontraron {$totalEquipos} equipos sin mantenimiento.");

        if($totalEquipos === 0) {
            $this->info('No hay equipos sin mantenimiento. Proceso finalizado.');
            return;
        }

        $fechaActual = Carbon::create(date('Y'),3,3);
        $contadorDiario = 0;
        $maxPorDia = 4;
        $actasCreadas = 0;



        $urlFirmaBd = ENV('APP_URL').'signatures/nG7eo0GkSldiEnYs3xcxHAofCTyW1RW8jtBZAQZc.png';
        
        foreach ($equipos as $equipo) {

            if ($fechaActual -> isWeekend()) {
                $fechaActual -> next(Carbon::MONDAY);
            }
           
            PcMantenimiento::create([
                'equipo_id' => $equipo->id,
                'tipo_mantenimiento' => 'preventivo',
                'descripcion' => 'Se realiza mantenimiento preventivo al equipo.',
                'fecha' => $fechaActual -> format('Y-m-d'),
                'empresa_responsable_id' => 2,
                'repuesto' => false,
                'cantidad_repuesto' => 0,
                'costo_repuesto' => 0.00,
                'nombre_repuesto' => null,
                'responsable_mantenimiento' => 7,
                'firma_personal_cargo' => null,
                'firma_sistemas' => $urlFirmaBd,

                'creado_por' => 7, 
                'fecha_creacion' => $fechaActual -> format('Y-m-d H:i:s'),
                'estado' => 'Pendiente',
                'cpu' => true,
                'pantalla' => true,
                'teclado' => true,
                'mouse' => true,
                'unidad_cd' => true,
            ]);

            $actasCreadas++;
            $contadorDiario++;
            if ($contadorDiario >= $maxPorDia) {
                $fechaActual->addDay();
                $contadorDiario = 0;
            }

        }
            
        $this->info("Proceso finalizado. Se han generado {$actasCreadas} actas de mantenimiento.");

    }
}
