<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Mail\InfraestructuraAlertMail;

class CheckInfraestructuraConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:infraestructura';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica la conexión con la IP Pública y el PC de Vacunación con ráfagas de 3 pings y filtro anti-flapping.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $alertEmailsRaw = config('services.monitoreo.alert_emails', env('MONITOR_ALERT_EMAILS', 'nicoleperez0958@gmail.com'));
        $alertEmails = array_filter(array_map('trim', explode(',', (string) $alertEmailsRaw)));

        if (empty($alertEmails)) {
            $this->warn('No hay correos electrónicos configurados en MONITOR_ALERT_EMAILS.');
            return;
        }

        $hosts = [
            'Servidor Principal (IP Pública IPS)' => config('services.monitoreo.ip_publica', '190.145.135.122'),
            'PC Vacunación' => config('services.monitoreo.pc_vacunacion', '192.168.0.240'),
        ];

        $this->info("Iniciando monitoreo de infraestructura para " . count($hosts) . " objetivos...");

        foreach ($hosts as $nombre => $host) {
            $this->line("--------------------------------------------------");
            $this->info("Evaluando {$nombre} [{$host}] con ráfaga de 3 pings...");

            $pingResult = $this->pingHost($host, 3);
            $cachePrefix = 'mon_infra_' . md5($host);

            $isCurrentlyDown = (bool) Cache::get("{$cachePrefix}_is_down", false);
            $downSince = Cache::get("{$cachePrefix}_down_since", null);
            $stillDownNotified = (bool) Cache::get("{$cachePrefix}_still_down_notified", false);

            if ($pingResult['alive']) {
                $this->info("✔ {$nombre} responde correctamente (Latencia: " . ($pingResult['latency'] ?: 'OK') . ").");

                if ($isCurrentlyDown) {
                    $this->info("{$nombre} se ha recuperado. Enviando correo de restablecimiento...");

                    $extraData = [
                        'latencia' => $pingResult['latency'],
                    ];

                    $this->sendAlert('up', $nombre, $host, $alertEmails, 3, $extraData);

                    Cache::put("{$cachePrefix}_is_down", false);
                    Cache::forget("{$cachePrefix}_down_since");
                    Cache::forget("{$cachePrefix}_still_down_notified");
                }
            } else {
                $this->error("✖ {$nombre} falló los 3 intentos consecutivos de ping.");

                if (!$isCurrentlyDown) {
                    $this->warn("Nueva caída confirmada. Enviando alerta crítica por correo...");

                    $this->sendAlert('down', $nombre, $host, $alertEmails, 3);

                    Cache::put("{$cachePrefix}_is_down", true);
                    Cache::put("{$cachePrefix}_down_since", now()->toIso8601String());
                    Cache::put("{$cachePrefix}_still_down_notified", false);
                } else {
                    if (!$stillDownNotified) {
                        $this->warn("A los 5 minutos el host sigue sin responder. Enviando correo de persistencia...");

                        $duracion = 'Aproximadamente 5 minutos';
                        if ($downSince) {
                            try {
                                $duracion = Carbon::parse($downSince)->diffForHumans(now(), [
                                    'parts' => 2,
                                    'syntax' => Carbon::DIFF_ABSOLUTE
                                ]);
                            } catch (\Throwable) {}
                        }

                        $extraData = [
                            'duracion_aprox' => $duracion,
                        ];

                        $this->sendAlert('still_down', $nombre, $host, $alertEmails, 3, $extraData);
                        Cache::put("{$cachePrefix}_still_down_notified", true);
                    } else {
                        $this->line("El host continúa caído. Silencio activo para evitar spam.");
                    }
                }
            }
        }

        $this->line("--------------------------------------------------");
        $this->info("Monitoreo finalizado.");
    }

    /**
     * Realiza una ráfaga de pings al host especificado.
     * Retorna inmediatamente al primer éxito para optimizar tiempo.
     */
    private function pingHost(string $host, int $attempts = 3): array
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $success = false;
        $latency = null;

        for ($i = 1; $i <= $attempts; $i++) {
            $cmd = $isWindows
                ? "ping -n 1 -w 2500 " . escapeshellarg($host)
                : "ping -c 1 -W 3 " . escapeshellarg($host);

            $output = [];
            $returnVar = 1;
            @exec($cmd, $output, $returnVar);

            if ($returnVar === 0) {
                $success = true;
                $outputStr = implode(' ', $output);
                if (preg_match('/(?:tiempo|time)[=<](\d+\.?\d*\s*ms)/i', $outputStr, $match)) {
                    $latency = $match[1];
                }
                break;
            }

            if ($i < $attempts) {
                sleep(1);
            }
        }

        return [
            'alive' => $success,
            'latency' => $latency,
        ];
    }

    /**
     * Envía la alerta por correo electrónico.
     */
    private function sendAlert(string $status, string $dispositivo, string $host, array $emails, int $intentos = 3, array $extraData = [])
    {
        try {
            Mail::to($emails)->send(new InfraestructuraAlertMail($status, $dispositivo, $host, $intentos, $extraData));
            $this->info("Correo de estado [{$status}] enviado exitosamente a: " . implode(', ', $emails));
        } catch (\Throwable $e) {
            $this->error("Error al enviar correo de alerta: " . $e->getMessage());
        }
    }
}
