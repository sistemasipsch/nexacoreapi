<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexaCore - Monitoreo de Infraestructura</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body style="font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; padding: 30px 15px; margin: 0; color: #1e293b; -webkit-font-smoothing: antialiased;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;">
        
        <!-- Header con gradiente original -->
        @php
            $headerGradient = match($status) {
                'down' => 'linear-gradient(135deg, #b91c1c 0%, #dc2626 50%, #ea580c 100%)',
                'still_down' => 'linear-gradient(135deg, #c2410c 0%, #d97706 50%, #b45309 100%)',
                'up' => 'linear-gradient(135deg, #15803d 0%, #16a34a 50%, #059669 100%)',
                default => 'linear-gradient(135deg, #4f46e5 0%, #6366f1 100%)'
            };
            $badgeBg = match($status) {
                'down' => '#fef2f2',
                'still_down' => '#fffbeb',
                'up' => '#f0fdf4',
                default => '#f8fafc'
            };
            $badgeText = match($status) {
                'down' => '#dc2626',
                'still_down' => '#d97706',
                'up' => '#16a34a',
                default => '#475569'
            };
            $badgeBorder = match($status) {
                'down' => '#fecaca',
                'still_down' => '#fde68a',
                'up' => '#bbf7d0',
                default => '#cbd5e1'
            };
            $statusLabel = match($status) {
                'down' => 'CRÍTICO: CONEXIÓN PERDIDA',
                'still_down' => 'ADVERTENCIA: PERSISTENCIA DE CAÍDA',
                'up' => 'OPERATIVO: CONEXIÓN RESTABLECIDA',
                default => 'ESTADO: INFORMACIÓN'
            };
            $icon = match($status) {
                'down' => '🚨',
                'still_down' => '⚠️',
                'up' => '✅',
                default => 'ℹ️'
            };

            $logoPath = public_path('logo.png');
            $logoSrc = '';
            if (file_exists($logoPath)) {
                $logoSrc = isset($message) ? $message->embed($logoPath) : 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
            }
        @endphp

        <div style="background: {{ $headerGradient }}; padding: 34px 32px; color: #ffffff;">
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
                <tr>
                    <td style="vertical-align: middle;">
                        <table style="border-collapse: collapse;">
                            <tr>
                                @if($logoSrc)
                                <td style="vertical-align: middle; padding-right: 10px;">
                                    <img src="{{ $logoSrc }}" alt="Nexa" style="height: 30px; width: 30px; object-fit: contain; border-radius: 6px; background-color: rgba(255,255,255,0.2); padding: 2px; display: block;">
                                </td>
                                @endif
                                <td style="vertical-align: middle;">
                                    <span style="font-size: 13px; font-weight: 700; color: #ffffff; letter-spacing: 0.05em; text-transform: uppercase;">
                                        NexaCore IPS
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="text-align: right; vertical-align: middle;">
                        <span style="display: inline-block; background-color: rgba(255, 255, 255, 0.18); border: 1px solid rgba(255, 255, 255, 0.28); border-radius: 9999px; padding: 4px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #ffffff;">
                            MONITOREO DE RED
                        </span>
                    </td>
                </tr>
            </table>

            <h1 style="margin: 0 0 6px 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px; line-height: 1.2;">
                {{ $icon }} {{ $dispositivo }}
            </h1>
            <p style="margin: 0; font-size: 14px; color: rgba(255, 255, 255, 0.92); font-weight: 400;">
                Host: <strong style="font-family: monospace; font-size: 15px;">{{ $host }}</strong>
                @if(!empty($extraData['equipo_info']['area_sede']))
                    &bull; {{ $extraData['equipo_info']['area_sede'] }}
                @endif
            </p>
        </div>

        <!-- Contenido principal -->
        <div style="padding: 28px 32px 20px 32px;">
            
            <!-- Badge de Estado -->
            <div style="display: inline-block; background-color: {{ $badgeBg }}; border: 1px solid {{ $badgeBorder }}; color: {{ $badgeText }}; font-weight: 700; font-size: 12px; padding: 6px 14px; border-radius: 8px; margin-bottom: 20px; letter-spacing: 0.05em;">
                {{ $statusLabel }}
            </div>

            <!-- Resumen en texto -->
            @if($status === 'down')
                <p style="margin: 0 0 20px 0; font-size: 15px; line-height: 1.6; color: #334155;">
                    Se ha detectado una <strong>interrupción de conectividad</strong>. Se realizaron <strong>{{ $intentos }} intentos de ping consecutivos</strong> con tiempo de espera agotado sin obtener respuesta del equipo o enlace.
                    @if(!empty($extraData['equipo_info']['responsable_nombre']))
                        Esta alerta ha sido notificada al equipo de Sistemas y al funcionario responsable asignado en el inventario.
                    @endif
                </p>
            @elseif($status === 'still_down')
                <p style="margin: 0 0 20px 0; font-size: 15px; line-height: 1.6; color: #334155;">
                    El enlace / equipo <strong>continúa sin responder</strong> a las solicitudes de ping tras una nueva verificación.
                    @if(!empty($extraData['duracion_aprox']))
                        Tiempo acumulado fuera de línea: <strong>{{ $extraData['duracion_aprox'] }}</strong>.
                    @endif
                </p>
            @else
                <p style="margin: 0 0 20px 0; font-size: 15px; line-height: 1.6; color: #334155;">
                    La conectividad con <strong>{{ $dispositivo }}</strong> se ha <strong>restablecido exitosamente</strong>. Los paquetes ICMP vuelven a responder de forma estable.
                </p>
            @endif

            <!-- Tabla de Detalles Técnicos -->
            <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; margin-bottom: 24px;">
                <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #64748b; margin-bottom: 12px;">
                    DETALLES DE LA VERIFICACIÓN
                </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <tr>
                        <td style="padding: 6px 0; color: #64748b; width: 42%;">Dispositivo / Servicio:</td>
                        <td style="padding: 6px 0; color: #0f172a; font-weight: 600;">{{ $dispositivo }}</td>
                    </tr>
                    @if(!empty($extraData['equipo_info']))
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Área / Sede:</td>
                        <td style="padding: 6px 0; color: #0f172a; font-weight: 500;">{{ $extraData['equipo_info']['area_sede'] }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Responsable en Acta:</td>
                        <td style="padding: 6px 0; color: #0f172a; font-weight: 600;">
                            {{ $extraData['equipo_info']['responsable_nombre'] }}
                            @if(!empty($extraData['equipo_info']['responsable_correo']))
                                <div style="font-size: 12px; color: #475569; font-weight: normal; font-family: monospace; margin-top: 2px;">
                                    {{ $extraData['equipo_info']['responsable_correo'] }}
                                </div>
                            @endif
                        </td>
                    </tr>
                    @if(!empty($extraData['equipo_info']['acta_id']))
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Acta de Entrega:</td>
                        <td style="padding: 6px 0; color: #0f172a; font-size: 13px;">
                            Acta #{{ $extraData['equipo_info']['acta_id'] }} @if(!empty($extraData['equipo_info']['fecha_entrega'])) ({{ $extraData['equipo_info']['fecha_entrega'] }}) @endif
                        </td>
                    </tr>
                    @endif
                    @endif
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Dirección IP / Host:</td>
                        <td style="padding: 6px 0; color: #0f172a; font-family: monospace; font-weight: 600;">{{ $host }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Prueba realizada:</td>
                        <td style="padding: 6px 0; color: #0f172a;">Ráfaga de {{ $intentos }} pings (ICMP)</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Resultado de ráfaga:</td>
                        <td style="padding: 6px 0; color: {{ $badgeText }}; font-weight: 700;">
                            @if($status === 'up')
                                Respondido exitosamente
                            @else
                                {{ $intentos }} de {{ $intentos }} paquetes perdidos (100% pérdida)
                            @endif
                        </td>
                    </tr>
                    @if(!empty($extraData['latencia']))
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Latencia observada:</td>
                        <td style="padding: 6px 0; color: #0f172a; font-weight: 600;">{{ $extraData['latencia'] }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Fecha y Hora Oficial:</td>
                        <td style="padding: 6px 0; color: #0f172a; font-weight: 500;">{{ $timestamp }}</td>
                    </tr>
                </table>
            </div>

        </div>

        <!-- Footer limpio -->
        <div style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 18px 32px; text-align: center;">
            <p style="margin: 0; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: #64748b;">
                NexaCore API &bull; Monitoreo de Infraestructura &bull; IPS Clinical House
            </p>
        </div>

    </div>
</body>
</html>
