<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewChatMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected $message
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $senderName = $this->message->sender->name ?? 'Usuário';
        $bodyText = $this->message->message
            ? "{$senderName}: {$this->message->message}"
            : "{$senderName} enviou uma imagem";

        return [
            'type' => 'new_chat_message',
            'title' => 'Nova Mensagem',
            'body' => $bodyText,
            'data' => [
                'type' => 'new_chat_message',
                'order_id' => (string) $this->message->order_id,
                'message_id' => (string) $this->message->id,
                'sender_id' => (string) $this->message->sender_id,
                'sender_name' => $senderName,
                'message' => json_encode([
                    'id' => $this->message->id,
                    'order_id' => $this->message->order_id,
                    'sender_id' => $this->message->sender_id,
                    'message' => $this->message->message,
                    'media_url' => $this->message->media_url,
                    'type' => $this->message->type,
                    'created_at' => $this->message->created_at?->toISOString(),
                    'sender' => [
                        'id' => $this->message->sender_id,
                        'name' => $senderName,
                        'avatar_url' => $this->message->sender->avatar_url ?? null,
                    ],
                ]),
            ],
        ];
    }
}
