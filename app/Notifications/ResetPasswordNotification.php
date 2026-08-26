<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $resetUrl = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        $viewData = [
            'actionUrl' => $resetUrl,
            'brandName' => config('app.name', 'Calle Sabor'),
            'expiresInMinutes' => $minutes,
            'logoPath' => public_path('assets/img/logo.png'),
            'logoUrl' => asset('assets/img/logo.png'),
            'userName' => $notifiable->name,
        ];

        return (new MailMessage)
            ->subject('Recupera el acceso a '.config('app.name'))
            ->view('mail.password-reset', $viewData)
            ->text('mail.password-reset-text', $viewData)
            ->action('Restablecer contraseña', $resetUrl);
    }
}
