<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisputeResolvedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected $dispute
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Disputa do pedido #{$this->dispute->order->order_number} resolvida")
            ->greeting('Olá!')
            ->line("A disputa referente ao pedido #{$this->dispute->order->order_number} foi resolvida.")
            ->line("Resolução: {$this->dispute->resolution}")
            ->action('Ver Detalhes', url("/pedidos/{$this->dispute->order_id}"))
            ->salutation('Atenciosamente, Equipe CarHub');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'dispute_resolved',
            'title' => 'Disputa Resolvida',
            'body' => "Disputa do pedido #{$this->dispute->order->order_number} resolvida",
            'data' => [
                'dispute_id' => $this->dispute->id,
                'order_id' => $this->dispute->order_id,
                'order_number' => $this->dispute->order->order_number,
                'resolution' => $this->dispute->resolution,
            ],
        ];
    }
}
