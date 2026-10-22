<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Importar fuente Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;700;900&display=swap" rel="stylesheet">
    <title>Nexa Alert - KUBAPP API</title>
</head>
<body style="font-family: 'Outfit', Arial, sans-serif; background-color: #f8fafc; padding: 40px 20px; margin: 0; -webkit-font-smoothing: antialiased;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 40px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        
        <!-- Nexa Hero Header -->
        <div style="background: #0284c7; background: linear-gradient(135deg, #0369a1 0%, #0284c7 50%, #0ea5e9 100%); padding: 48px; text-align: left;">
            <span style="display: inline-block; background-color: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); border-radius: 9999px; padding: 6px 16px; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.2em; color: #ffffff; margin-bottom: 24px;">
                MONITOREO DE SISTEMA & APIS
            </span>
            <h1 style="color: #ffffff; margin: 0 0 8px 0; font-size: 36px; font-weight: 900; letter-spacing: -1px; line-height: 1.1;">
                NexaCoreApi
            </h1> 
            <p style="color: rgba(255,255,255,0.9); margin: 0; font-size: 16px; font-weight: 500;">
                Estado de conectividad Servidor KubApp API
            </p>
        </div>

        <!-- Body -->
        <div style="padding: 40px;">
            @if($status === 'down')
                <div style="margin-bottom: 30px;">
                    <h2 style="color: #dc2626; margin: 0 0 10px 0; font-size: 24px; font-weight: 900; letter-spacing: -0.5px;">
                        🚨 ALERTA: Conexión o API Caída
                    </h2>
                    <p style="color: #0f172a; font-size: 16px; margin: 0; font-weight: 400; line-height: 1.6;">
                        Se detectó una interrupción en el servidor o en la respuesta de la API de KubApp.
                    </p>
                </div>

                <div style="background-color: #fef2f2; border-left: 6px solid #ef4444; border-radius: 24px; padding: 24px; margin-bottom: 30px;">
                    <div style="margin-bottom: 16px;">
                        <span style="display: block; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.2em; color: #94a3b8; margin-bottom: 4px;">Servidor / Host</span>
                        <span style="color: #7f1d1d; font-size: 16px; font-weight: 700;">{{ $host }}</span>
                    </div>
                    @if($url)
                    <div style="margin-bottom: 16px;">
                        <span style="display: block; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.2em; color: #94a3b8; margin-bottom: 4px;">Endpoint API</span>
                        <span style="color: #7f1d1d; font-size: 16px; font-weight: 700;">{{ $url }}</span>
                    </div>
                    @endif
                    @if($errorMessage)
                    <div style="margin-bottom: 16px;">
                        <span style="display: block; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.2em; color: #94a3b8; margin-bottom: 4px;">Detalle del Error</span>
                        <span style="color: #dc2626; font-size: 14px; font-weight: 600;">{{ $errorMessage }}</span>
                    </div>
                    @endif
                    <div>
                        <span style="display: block; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.2em; color: #94a3b8; margin-bottom: 4px;">Timestamp</span>
                        <span style="color: #7f1d1d; font-size: 16px; font-weight: 700;">{{ $timestamp }}</span>
                    </div>
                </div>
            @else
                <div style="margin-bottom: 30px;">
                    <h2 style="color: #16a34a; margin: 0 0 10px 0; font-size: 24px; font-weight: 900; letter-spacing: -0.5px;">
                        ✅ INFO: Conexión y API Operativas
                    </h2>
                    <p style="color: #0f172a; font-size: 16px; margin: 0; font-weight: 400; line-height: 1.6;">
                        Se ha restablecido la comunicación con el servidor y la API de KubApp responde satisfactoriamente.
                    </p>
                </div>

                <div style="background-color: #f0fdf4; border-left: 6px solid #22c55e; border-radius: 24px; padding: 24px; margin-bottom: 30px;">
                    <div style="margin-bottom: 16px;">
                        <span style="display: block; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.2em; color: #94a3b8; margin-bottom: 4px;">Servidor Restablecido</span>
                        <span style="color: #14532d; font-size: 16px; font-weight: 700;">{{ $host }}</span>
                    </div>
                    @if($url)
                    <div style="margin-bottom: 16px;">
                        <span style="display: block; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.2em; color: #94a3b8; margin-bottom: 4px;">Endpoint API</span>
                        <span style="color: #14532d; font-size: 16px; font-weight: 700;">{{ $url }}</span>
                    </div>
                    @endif
                    <div>
                        <span style="display: block; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.2em; color: #94a3b8; margin-bottom: 4px;">Timestamp</span>
                        <span style="color: #14532d; font-size: 16px; font-weight: 700;">{{ $timestamp }}</span>
                    </div>
                </div>
            @endif

        </div>

        <!-- Footer -->
        <div style="border-top: 1px solid #f1f5f9; padding: 24px; text-align: center; background-color: #ffffff;">
            <p style="color: #94a3b8; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.2em; margin: 0;">
                NexaCore API &bull; Monitoreo de Infraestructura y Servicios
            </p>
        </div>
    </div>
</body>
</html>
