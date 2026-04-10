<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuoteAdjustedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected $quote
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quote_adjusted',
            'title' => 'Orçamento Ajustado',
            'body' => "Orçamento ajustado para pedido #{$this->quote->order->order_number}",
            'data' => [
                'quote_id' => $this->quote->id,
                'order_id' => $this->quote->order_id,
                'order_number' => $this->quote->order->order_number,
            ],
        ];
    }
}
