<?php

namespace App\Mail;

use App\Models\PcEntrega;
use App\Models\PcEquipo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ActaEntregaMail extends Mailable
{
    use Queueable, SerializesModels;

    public PcEntrega $acta;
    public ?PcEquipo $equipo;
    public string $nombreFuncionario;
    public ?string $rutaPdfActa;
    public ?string $rutaPdfHojaVida;

    /**
     * Create a new message instance.
     */
    public function __construct(
        PcEntrega $acta,
        ?PcEquipo $equipo,
        string $nombreFuncionario,
        ?string $rutaPdfActa = null,
        ?string $rutaPdfHojaVida = null
    ) {
        $this->acta = $acta;
        $this->equipo = $equipo;
        $this->nombreFuncionario = $nombreFuncionario;
        $this->rutaPdfActa = $rutaPdfActa;
        $this->rutaPdfHojaVida = $rutaPdfHojaVida;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $identificador = $this->equipo?->nombre_equipo 
            ?? $this->equipo?->serial 
            ?? "Acta #{$this->acta->id}";

        return new Envelope(
            subject: "Acta de Entrega de Equipo de Cómputo [{$identificador}] - IPS Clinical House",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.actas.acta_entrega',
            text: 'emails.actas.acta_entrega_text',
            with: [
                'acta' => $this->acta,
                'equipo' => $this->equipo,
                'nombreFuncionario' => $this->nombreFuncionario,
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
        $attachments = [];

        if ($this->rutaPdfActa && file_exists($this->rutaPdfActa)) {
            $attachments[] = Attachment::fromPath($this->rutaPdfActa)
                ->as("Acta_Entrega_{$this->acta->id}.pdf")
                ->withMime('application/pdf');
        }

        if ($this->rutaPdfHojaVida && file_exists($this->rutaPdfHojaVida)) {
            $serial = !empty($this->equipo?->serial) ? $this->equipo->serial : 'Equipo_' . ($this->equipo?->id ?? 'HV');
            $attachments[] = Attachment::fromPath($this->rutaPdfHojaVida)
                ->as("Hoja_de_Vida_{$serial}.pdf")
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
