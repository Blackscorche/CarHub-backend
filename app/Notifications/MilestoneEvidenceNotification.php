<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MilestoneEvidenceNotification extends Notification
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
            'type' => 'milestone_evidence',
            'title' => 'Evidência Enviada',
            'body' => "O fornecedor enviou evidência para a etapa {$this->milestone->sequence} do pedido.",
            'data' => [
                'milestone_id' => $this->milestone->id,
                'order_id' => $this->milestone->order_id,
            ],
        ];
    }
}
