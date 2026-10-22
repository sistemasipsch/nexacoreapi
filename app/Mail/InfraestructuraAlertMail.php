<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InfraestructuraAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $status;
    public string $dispositivo;
    public string $host;
    public int $intentos;
    public array $extraData;

    /**
     * Create a new message instance.
     *
     * @param string $status 'down', 'still_down', 'up'
     * @param string $dispositivo Nombre descriptivo del equipo o enlace
     * @param string $host IP o dominio evaluado
     * @param int $intentos Número de pings realizados
     * @param array $extraData Información adicional (tiempo caído, error, latencia, etc.)
     */
    public function __construct(string $status, string $dispositivo, string $host, int $intentos = 3, array $extraData = [])
    {
        $this->status = $status;
        $this->dispositivo = $dispositivo;
        $this->host = $host;
        $this->intentos = $intentos;
        $this->extraData = $extraData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match ($this->status) {
            'down' => "🚨 ALERTA: Conexión Perdida en {$this->dispositivo}",
            'still_down' => "⚠️ PERSISTENCIA: Conexión Sin Restablecer en {$this->dispositivo}",
            'up' => "✅ RESTABLECIDO: Conexión Normalizada en {$this->dispositivo}",
            default => "ℹ️ Monitoreo de Conectividad NexaCore: {$this->dispositivo}",
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.infraestructura_alert',
            with: [
                'status' => $this->status,
                'dispositivo' => $this->dispositivo,
                'host' => $this->host,
                'intentos' => $this->intentos,
                'extraData' => $this->extraData,
                'timestamp' => now()->timezone('America/Bogota')->format('d/m/Y h:i:s A'),
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
