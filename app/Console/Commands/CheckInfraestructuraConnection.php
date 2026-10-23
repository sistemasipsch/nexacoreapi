<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Mail\InfraestructuraAlertMail;
use App\Models\PcEquipo;
use App\Models\PcEntrega;
use App\Models\Personal;
use App\Models\Usuario;

class CheckInfraestructuraConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:infraestructura {--host= : Host o IP específica a evaluar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica la conexión con el Servidor Principal y equipos de cómputo, notificando a Sistemas y al usuario a cargo en el acta de entrega.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $alertEmailsRaw = config('services.monitoreo.alert_emails', 'sistemasipsch2@gmail.com,sistemas@clinicalhouse.co,nicoleperez0958@gmail.com,junior.arias02yt@gmail.com');
        $baseAlertEmails = array_filter(array_map('trim', explode(',', (string) $alertEmailsRaw)));

        if (empty($baseAlertEmails)) {
            $this->warn('No hay correos electrónicos configurados en MONITOR_ALERT_EMAILS.');
            return;
        }

        // Si se pasa un host específico por parámetro
        $customHost = $this->option('host');

        if (!empty($customHost)) {
            $hosts = ['Objetivo Personalizado' => trim($customHost)];
        } else {
            $hosts = array_filter([
                'Servidor Principal (IP Pública IPS)' => config('services.monitoreo.ip_publica', '190.145.135.122'),
                'PC Vacunación' => config('services.monitoreo.pc_vacunacion', '192.168.0.240'),
            ]);
        }

        $this->info("Iniciando monitoreo de infraestructura para " . count($hosts) . " objetivos...");

        foreach ($hosts as $nombreDefecto => $host) {
            $this->line("--------------------------------------------------");

            // Resolver información del equipo en inventario y acta de entrega
            $equipoInfo = $this->resolveEquipoAndUser($host);
            $nombreDispositivo = $equipoInfo['dispositivo'] ?? $nombreDefecto;
            
            // Correos destinatarios para este dispositivo (Sistemas + Responsable en Acta)
            $targetEmails = $baseAlertEmails;
            if (!empty($equipoInfo['correo_responsable'])) {
                $targetEmails = array_values(array_unique(array_merge($baseAlertEmails, [$equipoInfo['correo_responsable']])));
                $this->line("👤 Responsable asignado: {$equipoInfo['equipo_info']['responsable_nombre']} ({$equipoInfo['correo_responsable']})");
            }

            $intentos = (int) config('services.monitoreo.intentos', env('MONITOR_INTENTOS', 3));
            $timeoutPing = (int) config('services.monitoreo.timeout', env('MONITOR_TIMEOUT', 3));

            $this->info("Evaluando {$nombreDispositivo} [{$host}] con ráfaga de {$intentos} pings (timeout: {$timeoutPing}s)...");

            $pingResult = $this->pingHost($host, $intentos, $timeoutPing);
            $cachePrefix = 'mon_infra_' . md5($host);

            $isCurrentlyDown = (bool) Cache::get("{$cachePrefix}_is_down", false);
            $downSince = Cache::get("{$cachePrefix}_down_since", null);
            $stillDownNotified = (bool) Cache::get("{$cachePrefix}_still_down_notified", false);

            if ($pingResult['alive']) {
                $this->info("✔ {$nombreDispositivo} responde correctamente (Latencia: " . ($pingResult['latency'] ?: 'OK') . ").");

                if ($isCurrentlyDown) {
                    $this->info("{$nombreDispositivo} se ha recuperado. Enviando correo de restablecimiento...");

                    $extraData = [
                        'latencia' => $pingResult['latency'],
                    ];
                    if (!empty($equipoInfo['equipo_info'])) {
                        $extraData['equipo_info'] = $equipoInfo['equipo_info'];
                    }

                    $this->sendAlert('up', $nombreDispositivo, $host, $targetEmails, 3, $extraData);

                    Cache::put("{$cachePrefix}_is_down", false);
                    Cache::forget("{$cachePrefix}_down_since");
                    Cache::forget("{$cachePrefix}_still_down_notified");
                }
            } else {
                $this->error("✖ {$nombreDispositivo} falló los 3 intentos consecutivos de ping.");

                $extraData = [];
                if (!empty($equipoInfo['equipo_info'])) {
                    $extraData['equipo_info'] = $equipoInfo['equipo_info'];
                }

                if (!$isCurrentlyDown) {
                    $this->warn("Nueva caída confirmada. Enviando alerta crítica por correo...");

                    $this->sendAlert('down', $nombreDispositivo, $host, $targetEmails, 3, $extraData);

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

                        $extraData['duracion_aprox'] = $duracion;

                        $this->sendAlert('still_down', $nombreDispositivo, $host, $targetEmails, 3, $extraData);
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
     * Resuelve el equipo en inventario, su acta de entrega activa y el usuario con su correo registrado.
     */
    private function resolveEquipoAndUser(string $host): array
    {
        try {
            $equipo = PcEquipo::with(['responsable', 'sede', 'area', 'entregas.funcionario'])
                ->where('ip_fija', $host)
                ->orWhere('ip_fija', 'LIKE', "%{$host}%")
                ->first();

            if (!$equipo) {
                return [
                    'dispositivo' => null,
                    'equipo_info' => null,
                    'correo_responsable' => null,
                ];
            }

            // Buscar la última entrega activa (sin fecha de devolución)
            $entrega = $equipo->entregas()
                ->whereNull('devuelto')
                ->latest('id')
                ->first();

            $personal = $entrega?->funcionario ?? $equipo->responsable;
            $usuario = null;

            if ($personal) {
                $usuario = $this->findUsuarioForPersonal($personal);
            }

            $dispositivo = $equipo->nombre_equipo;
            if ($equipo->area) {
                $dispositivo .= ' (' . $equipo->area->nombre . ')';
            }

            $areaSede = trim(($equipo->area?->nombre ?? '') . ($equipo->sede ? ' - ' . $equipo->sede->nombre : ''));

            return [
                'dispositivo' => $dispositivo,
                'equipo_info' => [
                    'nombre' => $equipo->nombre_equipo,
                    'tipo' => $equipo->tipo,
                    'marca_modelo' => trim(($equipo->marca ?? '') . ' ' . ($equipo->modelo ?? '')),
                    'area_sede' => $areaSede ?: 'No especificada',
                    'responsable_nombre' => $personal?->nombre ?? 'Sin asignar',
                    'responsable_cedula' => $personal?->cedula,
                    'responsable_correo' => $usuario?->correo,
                    'acta_id' => $entrega?->id,
                    'fecha_entrega' => $entrega?->fecha_entrega ? Carbon::parse($entrega->fecha_entrega)->format('d/m/Y') : null,
                ],
                'correo_responsable' => $usuario?->correo,
            ];
        } catch (\Throwable $e) {
            $this->warn("No se pudo consultar información del equipo para [{$host}]: " . $e->getMessage());
            return [
                'dispositivo' => null,
                'equipo_info' => null,
                'correo_responsable' => null,
            ];
        }
    }

    /**
     * Localiza de manera inteligente el registro de Usuario y su correo a partir del Personal.
     */
    private function findUsuarioForPersonal(Personal $personal): ?Usuario
    {
        // 1. Coincidencia directa por cédula en el campo usuario
        if (!empty($personal->cedula)) {
            $u = Usuario::where('usuario', trim($personal->cedula))
                ->whereNotNull('correo')
                ->where('correo', '!=', '')
                ->first();
            if ($u) {
                return $u;
            }
        }

        // 2. Coincidencia exacta de nombre completo
        if (!empty($personal->nombre)) {
            $u = Usuario::whereRaw('LOWER(TRIM(nombre_completo)) = LOWER(TRIM(?))', [trim($personal->nombre)])
                ->whereNotNull('correo')
                ->where('correo', '!=', '')
                ->first();
            if ($u) {
                return $u;
            }
        }

        // 3. Coincidencia por conjunto de palabras del nombre (ignorando orden de nombres/apellidos)
        $cleanWords = function ($str) {
            $str = mb_strtolower(trim((string) $str), 'UTF-8');
            $str = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $str);
            $words = preg_split('/\s+/', $str);
            $words = array_filter($words, fn($w) => strlen($w) >= 3);
            sort($words);
            return array_values($words);
        };

        $targetWords = $cleanWords($personal->nombre);
        if (empty($targetWords)) {
            return null;
        }

        $usuarios = Usuario::whereNotNull('correo')->where('correo', '!=', '')->get();
        $bestMatch = null;
        $maxMatches = 0;

        foreach ($usuarios as $u) {
            $uWords = $cleanWords($u->nombre_completo);
            $common = array_intersect($targetWords, $uWords);
            $count = count($common);

            if ($count === count($targetWords) && count($targetWords) > 1) {
                return $u;
            }

            if ($count >= 2 && $count > $maxMatches) {
                $maxMatches = $count;
                $bestMatch = $u;
            }
        }

        return $bestMatch;
    }

    /**
     * Realiza una ráfaga de pings al host especificado.
     * Retorna inmediatamente al primer éxito para optimizar tiempo.
     */
    private function pingHost(string $host, int $attempts = 3, int $timeoutSeconds = 3): array
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $success = false;
        $latency = null;
        $winTimeoutMs = $timeoutSeconds * 1000;

        for ($i = 1; $i <= $attempts; $i++) {
            $cmd = $isWindows
                ? "ping -n 1 -w {$winTimeoutMs} " . escapeshellarg($host)
                : "ping -c 1 -W {$timeoutSeconds} " . escapeshellarg($host);

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
