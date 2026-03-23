<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeliveryStartedNotification extends Notification
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
            'type' => 'delivery_started',
            'title' => 'Entrega Iniciada',
            'body' => "Entrega iniciada para pedido #{$this->order->order_number}",
            'data' => [
                'order_id' => $this->order->id,
                'order_number' => $this->order->order_number,
            ],
        ];
    }
}
