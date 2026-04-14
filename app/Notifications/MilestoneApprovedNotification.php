<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MilestoneApprovedNotification extends Notification
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
            'type' => 'milestone_approved',
            'title' => 'Etapa Aprovada',
            'body' => "Cliente aprovou a etapa {$this->milestone->sequence}. R$ " . number_format($this->milestone->amount, 2, ',', '.') . " liberado.",
            'data' => [
                'milestone_id' => $this->milestone->id,
                'order_id' => $this->milestone->order_id,
                'amount' => $this->milestone->amount,
            ],
        ];
    }
}
