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

        return (new MailMessage)
            ->subject('Recupera el acceso a '.config('app.name'))
            ->greeting('Hola, '.$notifiable->name)
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
            ->action('Crear una nueva contraseña', $resetUrl)
            ->line("Este enlace vence en {$minutes} minutos y solo puede utilizarse una vez.")
            ->line('Si no solicitaste este cambio, puedes ignorar este correo de forma segura.')
            ->salutation('Equipo de '.config('app.name'));
    }
}
