<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStartedNotification extends Notification
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
            'type' => 'order_started',
            'title' => 'Trabalho Iniciado',
            'body' => "Trabalho iniciado no pedido #{$this->order->order_number}",
            'data' => [
                'order_id' => $this->order->id,
                'order_number' => $this->order->order_number,
            ],
        ];
    }
}
