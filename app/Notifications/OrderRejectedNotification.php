<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected $order
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', FcmChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Pedido #{$this->order->order_number} recusado")
            ->greeting('Olá!')
            ->line("Infelizmente, seu pedido #{$this->order->order_number} foi recusado pelo fornecedor.")
            ->line('Você pode tentar enviar o pedido para outro fornecedor.')
            ->action('Ver Pedido', url("/pedidos/{$this->order->id}"))
            ->salutation('Atenciosamente, Equipe CarHub');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_rejected',
            'title' => 'Pedido Recusado',
            'body' => "Pedido #{$this->order->order_number} recusado",
            'data' => [
                'order_id' => $this->order->id,
                'order_number' => $this->order->order_number,
            ],
        ];
    }
}
