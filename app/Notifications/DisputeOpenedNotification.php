<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DisputeOpenedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected $dispute
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'dispute_opened',
            'title' => 'Disputa Aberta',
            'body' => "Disputa aberta no pedido #{$this->dispute->order->order_number}",
            'data' => [
                'dispute_id' => $this->dispute->id,
                'order_id' => $this->dispute->order_id,
                'order_number' => $this->dispute->order->order_number,
            ],
        ];
    }
}
