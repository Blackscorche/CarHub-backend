<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected $user
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', FcmChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sua conta foi aprovada! Bem-vindo ao CarHub')
            ->greeting("Olá, {$this->user->name}!")
            ->line('Sua conta de fornecedor foi aprovada com sucesso.')
            ->line('Agora você pode começar a receber pedidos e oferecer seus serviços na plataforma.')
            ->action('Acessar Painel', url('/fornecedor/painel'))
            ->salutation('Atenciosamente, Equipe CarHub');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'supplier_approved',
            'title' => 'Conta Aprovada',
            'body' => 'Sua conta foi aprovada! Bem-vindo ao CarHub.',
            'data' => [
                'user_id' => $this->user->id,
            ],
        ];
    }
}
