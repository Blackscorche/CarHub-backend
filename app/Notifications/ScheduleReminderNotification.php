<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ScheduleReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected $schedule
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $time = \Carbon\Carbon::parse($this->schedule->scheduled_at)->format('H:i');

        return [
            'type' => 'schedule_reminder',
            'title' => 'Lembrete de Agendamento',
            'body' => "Lembrete: agendamento amanhã às {$time}",
            'data' => [
                'schedule_id' => $this->schedule->id,
                'order_id' => $this->schedule->order_id,
                'scheduled_at' => $this->schedule->scheduled_at,
            ],
        ];
    }
}
