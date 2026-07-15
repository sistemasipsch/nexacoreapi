<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\SitradConnectionAlert;

class CheckSitradConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:sitrad';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica la conexión con los dispositivos Sitrad y envía alertas si fallan.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $alertEmailRaw = env('SITRAD_ALERT_EMAIL');
        
        if (!$alertEmailRaw) {
            $this->warn('Email de alerta para SITRAD no está configurado (SITRAD_ALERT_EMAIL en .env).');
            return;
        }

        $alertEmails = array_filter(array_map('trim', explode(',', $alertEmailRaw)));

        if (empty($alertEmails)) {
            $this->warn('No hay correos electrónicos válidos configurados para alertas SITRAD.');
            return;
        }

        $areas = [
            'Área de Vacunación' => env('SITRAD_HOST_VACUNACION', '190.145.135.122:8001'),
            'Área de Farmacia' => env('SITRAD_HOST_FARMACIA', '190.145.135.122:8002'),
        ];

        $failedAreas = [];

        foreach ($areas as $area => $host) {
            $this->info("Verificando conexión con Sitrad - {$area} ({$host})");
            list($ip, $port) = explode(':', $host);
            
            // Timeout de 5 segundos
            $connection = @fsockopen($ip, $port, $errno, $errstr, 5);

            if (is_resource($connection)) {
                fclose($connection);
                $this->info("{$area} está funcionando correctamente.");
                
                // Si estaba marcado como caído, enviar email de recuperación
                $cacheKey = "sitrad_" . md5($area) . "_is_down";
                $isCurrentlyDown = Cache::get($cacheKey, false);
                
                if ($isCurrentlyDown) {
                    $this->info("{$area} se ha recuperado. Enviando correo de recuperación...");
                    $this->sendAlertEmail('up', $area, $host, $alertEmails);
                    Cache::put($cacheKey, false);
                }
            } else {
                $this->error("{$area} falló al conectar.");
                $failedAreas[$area] = [
                    'host' => $host,
                    'error' => $errstr ?: 'Connection timeout o refused'
                ];
            }
        }

        // Enviar alertas para las áreas que están caídas
        foreach ($failedAreas as $area => $data) {
            $cacheKey = "sitrad_" . md5($area) . "_is_down";
            $isCurrentlyDown = Cache::get($cacheKey, false);
            
            if (!$isCurrentlyDown) {
                $this->info("Detectada nueva caída. Enviando correo de alerta de caída para {$area}...");
                $this->sendAlertEmail('down', $area, $data['host'], $alertEmails);
                Cache::put($cacheKey, true);
            } else {
                $this->info("{$area} sigue caída. No se envía nuevo correo para evitar spam.");
            }
        }
    }

    private function sendAlertEmail(string $status, string $area, string $host, array $emails)
    {
        try {
            Mail::to($emails)->send(new SitradConnectionAlert($status, $area, $host));
        } catch (\Exception $e) {
            $this->error("No se pudo enviar el correo de alerta de SITRAD: " . $e->getMessage());
        }
    }
}
