<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderPaidNotification extends Notification
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
            'type' => 'order_paid',
            'title' => 'Pagamento Confirmado',
            'body' => "Pedido #{$this->order->order_number} foi pago - R$ " . number_format($this->order->total, 2, ',', '.') . ". Você pode iniciar o serviço.",
            'data' => [
                'order_id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'total' => $this->order->total,
            ],
        ];
    }
}
