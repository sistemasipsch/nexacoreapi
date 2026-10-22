<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KubappConnectionAlert extends Mailable
{
    use Queueable, SerializesModels;

    public string $status;
    public string $host;
    public string $url;
    public string $errorMessage;

    /**
     * Create a new message instance.
     */
    public function __construct(string $status, string $host, string $url = '', string $errorMessage = '')
    {
        $this->status = $status;
        $this->host = $host;
        $this->url = $url;
        $this->errorMessage = $errorMessage;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->status === 'down' 
            ? "🚨 ALERTA KUBAPP: Servidor / API Fuera de Servicio ({$this->host})"
            : "✅ INFO KUBAPP: Servidor / API Restablecida ({$this->host})";

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
            view: 'emails.kubapp_alert',
            with: [
                'timestamp' => now()->timezone('America/Bogota')->format('d/m/Y h:i A'),
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
