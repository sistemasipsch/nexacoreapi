<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SitradConnectionAlert extends Mailable
{
    use Queueable, SerializesModels;

    public $status;
    public $area;
    public $host;

    /**
     * Create a new message instance.
     */
    public function __construct($status, $area, $host)
    {
        $this->status = $status;
        $this->area = $area;
        $this->host = $host;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->status === 'down' 
            ? "🚨 ALERTA SITRAD: Pérdida de Conexión en {$this->area}"
            : "✅ INFO SITRAD: Conexión Recuperada en {$this->area}";

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
            view: 'emails.sitrad_alert',
            with: [
                'timestamp' => now()->format('d/m/Y h:i A'),
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
