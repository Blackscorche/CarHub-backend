<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected $order
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_confirmed',
            'title' => 'Pedido Confirmado',
            'body' => "Pedido #{$this->order->order_number} confirmado. Pagamento liberado.",
            'data' => [
                'order_id' => $this->order->id,
                'order_number' => $this->order->order_number,
            ],
        ];
    }
}
