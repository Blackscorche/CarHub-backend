<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WithdrawalFailedNotification extends Notification
{
    use Queueable;

    public function __construct(protected $withdrawal) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'withdrawal_failed',
            'title' => 'Saque Falhou',
            'body' => "Saque de R$ " . number_format($this->withdrawal->amount, 2, ',', '.') . " falhou. Tentaremos novamente.",
            'data' => [
                'withdrawal_id' => $this->withdrawal->id,
                'amount' => $this->withdrawal->amount,
                'failure_reason' => $this->withdrawal->failure_reason,
            ],
        ];
    }
}
