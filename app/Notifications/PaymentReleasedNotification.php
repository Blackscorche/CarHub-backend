<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentReleasedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected $payment
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_released',
            'title' => 'Pagamento Liberado',
            'body' => "Pagamento de R$ " . number_format($this->payment->amount, 2, ',', '.') . " liberado",
            'data' => [
                'payment_id' => $this->payment->id,
                'amount' => $this->payment->amount,
            ],
        ];
    }
}
