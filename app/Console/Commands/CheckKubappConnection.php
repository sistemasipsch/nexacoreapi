<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Mail\KubappConnectionAlert;

class CheckKubappConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:kubapp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica la conexión con el servidor y la API de KubApp con ráfaga anti-flapping de reintentos parametrizable y filtro anti-spam.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $host = config('services.kubapp.server_api', env('SERVIDOR_KUBAPP_API', '190.145.135.122:3006'));
        $alertEmailsRaw = config('services.kubapp.alert_emails', env('KUBAPP_ALERT_EMAILS', env('MICROSERVICIO_ALERT_EMAIL')));

        // Parámetros configurables desde .env
        $maxAttempts = (int) config('services.kubapp.monitor_intentos', env('KUBAPP_MONITOR_INTENTOS', 3));
        $timeoutSeconds = (int) config('services.kubapp.monitor_timeout', env('KUBAPP_MONITOR_TIMEOUT', 4));
        $pauseSeconds = (int) config('services.kubapp.monitor_pausa', env('KUBAPP_MONITOR_PAUSA', 2));

        if (!$host) {
            $this->warn('El host del servidor KubApp API no está configurado (SERVIDOR_KUBAPP_API en .env).');
            return;
        }

        $alertEmails = array_filter(array_map('trim', explode(',', (string) $alertEmailsRaw)));

        if (empty($alertEmails)) {
            $this->warn('No hay correos electrónicos válidos configurados para alertas de KubApp API.');
            return;
        }

        $this->info("Verificando servidor y API de KubApp en: {$host}");
        $this->line("Parámetros: {$maxAttempts} intentos, timeout {$timeoutSeconds}s, pausa {$pauseSeconds}s");

        $apiUrl = "http://{$host}/api";
        $cachePrefix = 'kubapp_api';
        $isCurrentlyDown = (bool) Cache::get("{$cachePrefix}_is_down", false);
        $downSince = Cache::get("{$cachePrefix}_down_since", null);
        $stillDownNotified = (bool) Cache::get("{$cachePrefix}_still_down_notified", false);

        $parts = explode(':', $host);
        $ip = $parts[0];
        $port = isset($parts[1]) ? (int) $parts[1] : 80;

        // Evaluación con ráfaga de intentos parametrizada (Anti-Flapping)
        $health = $this->evaluateHealthWithRetry($ip, $port, $apiUrl, $maxAttempts, $timeoutSeconds, $pauseSeconds);

        if ($health['alive']) {
            $this->info("✔ KubApp API responde correctamente en el intento {$health['success_attempt']}/{$maxAttempts} (HTTP Status: {$health['status_code']}).");

            if ($isCurrentlyDown) {
                $this->info('El servidor y API de KubApp se han restablecido. Enviando correo de recuperación...');
                $this->sendAlertEmail('up', $host, $apiUrl, $alertEmails);
                Cache::put("{$cachePrefix}_is_down", false);
                Cache::forget("{$cachePrefix}_down_since");
                Cache::forget("{$cachePrefix}_still_down_notified");
            } else {
                $this->info('El servicio KubApp API está funcionando con normalidad.');
            }
        } else {
            $this->error("✖ KubApp API falló los {$maxAttempts} intentos consecutivos. Detalle: {$health['error']}");

            if (!$isCurrentlyDown) {
                $this->warn("Nueva caída confirmada tras {$maxAttempts} intentos. Enviando alerta por correo...");
                $this->sendAlertEmail('down', $host, $apiUrl, $alertEmails, $health['error']);
                Cache::put("{$cachePrefix}_is_down", true);
                Cache::put("{$cachePrefix}_down_since", now()->toIso8601String());
                Cache::put("{$cachePrefix}_still_down_notified", false);
            } else {
                if (!$stillDownNotified) {
                    $this->warn('A los 5 minutos sigue sin responder. Enviando correo de persistencia...');
                    $duracion = 'Aproximadamente 5 minutos';
                    if ($downSince) {
                        try {
                            $duracion = Carbon::parse($downSince)->diffForHumans(now(), [
                                'parts' => 2,
                                'syntax' => Carbon::DIFF_ABSOLUTE,
                            ]);
                        } catch (\Throwable) {}
                    }
                    $errorConDuracion = $health['error'] . " (Tiempo transcurrido: {$duracion})";
                    $this->sendAlertEmail('down', $host, $apiUrl, $alertEmails, $errorConDuracion);
                    Cache::put("{$cachePrefix}_still_down_notified", true);
                } else {
                    $this->line('El servicio continúa caído. Silencio activo activado para evitar saturación de correo.');
                }
            }
        }
    }

    /**
     * Realiza intentos de socket TCP y petición HTTP parametrizados con pausas intermedias.
     * Si al menos 1 intento tiene éxito, se considera ONLINE (evita falsos positivos por micro-cortes).
     */
    private function evaluateHealthWithRetry(string $ip, int $port, string $apiUrl, int $maxAttempts, int $timeoutSeconds, int $pauseSeconds): array
    {
        $lastError = '';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            // 1. Comprobación a nivel Socket TCP
            $socket = @fsockopen($ip, $port, $errno, $errstr, $timeoutSeconds);
            if (!is_resource($socket)) {
                $lastError = "Fallo socket TCP ({$ip}:{$port}): " . ($errstr ?: 'Connection timeout/refused');
                if ($attempt < $maxAttempts && $pauseSeconds > 0) {
                    sleep($pauseSeconds);
                }
                continue;
            }
            fclose($socket);

            // 2. Comprobación a nivel HTTP (API respondiendo)
            try {
                $response = Http::timeout($timeoutSeconds)->get($apiUrl);
                if ($response->status() > 0) {
                    return [
                        'alive' => true,
                        'status_code' => $response->status(),
                        'success_attempt' => $attempt,
                        'error' => null,
                    ];
                }
                $lastError = "Status HTTP inválido: " . $response->status();
            } catch (\Throwable $e) {
                $lastError = "API no respondió vía HTTP: " . $e->getMessage();
            }

            if ($attempt < $maxAttempts && $pauseSeconds > 0) {
                sleep($pauseSeconds);
            }
        }

        return [
            'alive' => false,
            'status_code' => null,
            'success_attempt' => null,
            'error' => $lastError,
        ];
    }

    private function sendAlertEmail(string $status, string $host, string $apiUrl, array $emails, string $errorMessage = ''): void
    {
        try {
            Mail::to($emails)->send(new KubappConnectionAlert($status, $host, $apiUrl, $errorMessage));
            $this->info("Notificación [{$status}] enviada a: " . implode(', ', $emails));
        } catch (\Throwable $e) {
            $this->error("Error enviando correo de alerta KubApp: " . $e->getMessage());
        }
    }
}
