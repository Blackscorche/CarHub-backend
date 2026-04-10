<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected $order
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_completed',
            'title' => 'Trabalho Concluído',
            'body' => "Trabalho concluído no pedido #{$this->order->order_number}",
            'data' => [
                'order_id' => $this->order->id,
                'order_number' => $this->order->order_number,
            ],
        ];
    }
}
