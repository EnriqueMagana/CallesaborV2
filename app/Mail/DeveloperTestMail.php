<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeveloperTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $testerName,
        public readonly string $sentAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Prueba de correo · '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.developer-test',
            text: 'mail.developer-test-text',
            with: [
                'brandName' => config('app.name', 'Calle Sabor'),
                'logoPath' => public_path('assets/img/logo.png'),
                'logoUrl' => asset('assets/img/logo.png'),
            ],
        );
    }
}
