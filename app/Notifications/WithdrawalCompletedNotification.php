<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WithdrawalCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(protected $withdrawal) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'withdrawal_completed',
            'title' => 'Saque Concluído',
            'body' => "Saque de R$ " . number_format($this->withdrawal->net_amount, 2, ',', '.') . " enviado para sua conta.",
            'data' => [
                'withdrawal_id' => $this->withdrawal->id,
                'amount' => $this->withdrawal->amount,
                'net_amount' => $this->withdrawal->net_amount,
            ],
        ];
    }
}
