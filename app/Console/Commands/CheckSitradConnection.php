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
    protected $description = 'Verifica la conexión con los dispositivos Sitrad con ráfaga anti-flapping de reintentos parametrizable y envía alertas si fallan.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $alertEmailRaw = config('services.sitrad.alert_emails', env('SITRAD_ALERT_EMAIL'));
        
        // Parámetros configurables desde .env
        $maxAttempts = (int) config('services.sitrad.monitor_intentos', env('SITRAD_MONITOR_INTENTOS', 3));
        $timeoutSeconds = (int) config('services.sitrad.monitor_timeout', env('SITRAD_MONITOR_TIMEOUT', 4));
        $pauseSeconds = (int) config('services.sitrad.monitor_pausa', env('SITRAD_MONITOR_PAUSA', 2));

        if (!$alertEmailRaw) {
            $this->warn('Email de alerta para SITRAD no está configurado (SITRAD_ALERT_EMAIL en .env).');
            return;
        }

        $alertEmails = array_filter(array_map('trim', explode(',', (string) $alertEmailRaw)));

        if (empty($alertEmails)) {
            $this->warn('No hay correos electrónicos válidos configurados para alertas SITRAD.');
            return;
        }

        $areas = [
            'Área de Vacunación' => config('services.sitrad.host_vacunacion', env('SITRAD_HOST_VACUNACION', '190.145.135.122:8001')),
            'Área de Farmacia' => config('services.sitrad.host_farmacia', env('SITRAD_HOST_FARMACIA', '190.145.135.122:8002')),
        ];

        $failedAreas = [];

        foreach ($areas as $area => $host) {
            $this->info("Verificando conexión con Sitrad - {$area} ({$host})");
            $this->line("Parámetros: {$maxAttempts} intentos, timeout {$timeoutSeconds}s, pausa {$pauseSeconds}s");
            list($ip, $port) = explode(':', $host);

            $isConnected = false;
            $lastError = 'Connection timeout o refused';

            // Rafaga de rententos parametrizable espaciada para evitar falsos positivos
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $connection = @fsockopen($ip, (int) $port, $errno, $errstr, $timeoutSeconds);

                if (is_resource($connection)) {
                    fclose($connection);
                    $isConnected = true;
                    $this->line("✔ Conexión exitosa en intento {$attempt}/{$maxAttempts}");
                    break;
                }

                $lastError = $errstr ?: 'Connection timeout o refused';
                if ($attempt < $maxAttempts && $pauseSeconds > 0) {
                    sleep($pauseSeconds);
                }
            }

            $cacheKey = "sitrad_" . md5($area) . "_is_down";
            $isCurrentlyDown = (bool) Cache::get($cacheKey, false);

            if ($isConnected) {
                $this->info("✔ {$area} está funcionando correctamente.");
                
                // Si estaba marcado como caído, enviar email de recuperación
                if ($isCurrentlyDown) {
                    $this->info("{$area} se ha recuperado. Enviando correo de recuperación...");
                    $this->sendAlertEmail('up', $area, $host, $alertEmails);
                    Cache::put($cacheKey, false);
                }
            } else {
                $this->error("✖ {$area} falló los {$maxAttempts} intentos consecutivos de conexión.");
                $failedAreas[$area] = [
                    'host' => $host,
                    'error' => $lastError
                ];
            }
        }

        // Enviar alertas para las áreas que están caídas (con silencio anti-spam si persiste)
        foreach ($failedAreas as $area => $data) {
            $cacheKey = "sitrad_" . md5($area) . "_is_down";
            $isCurrentlyDown = (bool) Cache::get($cacheKey, false);
            
            if (!$isCurrentlyDown) {
                $this->warn("Detectada nueva caída confirmada para {$area}. Enviando correo de alerta...");
                $this->sendAlertEmail('down', $area, $data['host'], $alertEmails);
                Cache::put($cacheKey, true);
            } else {
                $this->info("{$area} continúa caída. Silencio activo para evitar spam.");
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
