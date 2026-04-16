<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deepLink = sprintf(
            'carhub://reset-password?token=%s&email=%s',
            $this->token,
            urlencode($notifiable->getEmailForPasswordReset()),
        );

        return (new MailMessage)
            ->subject('CarHub — Redefinição de senha')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Recebemos um pedido para redefinir a senha da sua conta CarHub.')
            ->action('Redefinir senha no app', $deepLink)
            ->line('Este link expira em 60 minutos.')
            ->line('Se você não solicitou, ignore este e-mail — sua senha continua a mesma.')
            ->salutation('Equipe CarHub');
    }
}
