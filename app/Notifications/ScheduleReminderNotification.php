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
        $date = \Carbon\Carbon::parse($this->schedule->scheduled_date)->format('d/m');
        $time = substr((string) $this->schedule->scheduled_time, 0, 5);
        $scheduledAt = \Carbon\Carbon::parse(
            $this->schedule->scheduled_date . ' ' . $this->schedule->scheduled_time
        )->toIso8601String();

        return [
            'type' => 'schedule_reminder',
            'title' => 'Lembrete de Agendamento',
            'body' => "Lembrete: agendamento em {$date} às {$time}",
            'data' => [
                'schedule_id' => $this->schedule->id,
                'order_id' => $this->schedule->order_id,
                'scheduled_at' => $scheduledAt,
            ],
        ];
    }
}
