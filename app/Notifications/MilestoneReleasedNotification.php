<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MilestoneReleasedNotification extends Notification
{
    use Queueable;

    public function __construct(protected $milestone) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'milestone_released',
            'title' => 'Pagamento Liberado',
            'body' => "R$ " . number_format($this->milestone->amount, 2, ',', '.') . " foi liberado para sua conta.",
            'data' => [
                'milestone_id' => $this->milestone->id,
                'order_id' => $this->milestone->order_id,
                'amount' => $this->milestone->amount,
            ],
        ];
    }
}
