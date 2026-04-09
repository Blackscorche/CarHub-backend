<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuoteReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected $quote
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quote_received',
            'title' => 'Novo Orçamento',
            'body' => "Novo orçamento recebido para pedido #{$this->quote->order->order_number}",
            'data' => [
                'quote_id' => $this->quote->id,
                'order_id' => $this->quote->order_id,
                'order_number' => $this->quote->order->order_number,
            ],
        ];
    }
}
