<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $invitationUrl,
        public readonly string $roleLabel,
        public readonly string $invitedByName,
        public readonly string $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitación al equipo de '.config('app.name').' · '.$this->roleLabel,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.user-invitation',
            text: 'mail.user-invitation-text',
            with: [
                'brandName' => config('app.name', 'Calle Sabor'),
                'logoPath' => public_path('assets/img/logo.png'),
                'logoUrl' => asset('assets/img/logo.png'),
            ],
        );
    }
}
