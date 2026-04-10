<?php

namespace App\Channels;

use App\Services\FcmService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    protected FcmService $fcm;

    public function __construct(FcmService $fcm)
    {
        $this->fcm = $fcm;
    }

    public function send(object $notifiable, Notification $notification): void
    {
        $data = $notification->toArray($notifiable);

        $this->fcm->sendToUser(
            $notifiable,
            $data['title'] ?? 'CarHub',
            $data['body'] ?? '',
            $data['data'] ?? []
        );
    }
}
