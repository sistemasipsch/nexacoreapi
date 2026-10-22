<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
    protected $description = 'Verifica la conexión con el servidor y la API de KubApp (socket TCP y respuesta HTTP) y envía alertas si falla.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $host = config('services.kubapp.server_api', env('SERVIDOR_KUBAPP_API', '190.145.135.122:3006'));
        $alertEmailsRaw = config('services.kubapp.alert_emails', env('KUBAPP_ALERT_EMAILS', env('MICROSERVICIO_ALERT_EMAIL')));

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

        $apiUrl = "http://{$host}/api";
        $cacheKey = 'kubapp_api_is_down';
        $isCurrentlyDown = (bool) Cache::get($cacheKey, false);

        // Paso 1: Validación a nivel Socket TCP (puerto abierto)
        $parts = explode(':', $host);
        $ip = $parts[0];
        $port = isset($parts[1]) ? (int) $parts[1] : 80;

        $socketConnection = @fsockopen($ip, $port, $errno, $errstr, 5);

        if (!is_resource($socketConnection)) {
            $errorMsg = "Fallo de conexión socket TCP al puerto {$port}: " . ($errstr ?: 'Connection refused/timeout');
            $this->error("✖ {$errorMsg}");
            $this->handleFailure($host, $apiUrl, $errorMsg, $alertEmails, $isCurrentlyDown, $cacheKey);
            return;
        }

        fclose($socketConnection);
        $this->info("✔ Conexión TCP socket establecida exitosamente con {$ip}:{$port}");

        // Paso 2: Validación a nivel HTTP (API levantada y respondiendo)
        try {
            $response = Http::timeout(5)->get($apiUrl);

            // Cualquier código de respuesta HTTP (incluso 403 o 401 por autenticación) indica que el proceso Express / Node.js está levantado y atendiendo peticiones
            if ($response->status() > 0) {
                $this->info("✔ API KubApp responde correctamente (HTTP Status: " . $response->status() . ").");

                if ($isCurrentlyDown) {
                    $this->info('El servidor y API de KubApp se han restablecido. Enviando correo de recuperación...');
                    $this->sendAlertEmail('up', $host, $apiUrl, $alertEmails);
                    Cache::put($cacheKey, false);
                } else {
                    $this->info('El servicio KubApp API está funcionando con normalidad.');
                }
            } else {
                throw new \Exception("La API respondió con status code: " . $response->status());
            }

        } catch (\Throwable $e) {
            $errorMsg = "El puerto está abierto pero la API no responde vía HTTP: " . $e->getMessage();
            $this->error("✖ {$errorMsg}");
            $this->handleFailure($host, $apiUrl, $errorMsg, $alertEmails, $isCurrentlyDown, $cacheKey);
        }
    }

    private function handleFailure(string $host, string $apiUrl, string $errorMsg, array $alertEmails, bool $isCurrentlyDown, string $cacheKey): void
    {
        if (!$isCurrentlyDown) {
            $this->warn('Detectada nueva caída de KubApp API. Enviando correo de alerta...');
            $this->sendAlertEmail('down', $host, $apiUrl, $alertEmails, $errorMsg);
            Cache::put($cacheKey, true);
        } else {
            $this->info('KubApp API sigue caída. Silenciado para evitar saturación de correo.');
        }
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
