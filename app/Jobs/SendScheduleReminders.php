<?php

namespace App\Jobs;

use App\Models\PlatformConfig;
use App\Models\Schedule;
use App\Notifications\ScheduleReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendScheduleReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Lead time is configurable via PlatformConfig (default: 24h).
        $leadHours = (int) PlatformConfig::getValue('schedule_reminder_lead_hours', 24);

        $now = now();
        $windowEnd = (clone $now)->addHours($leadHours);

        // Pick all unnotified confirmed schedules whose datetime is within [now, now + lead_hours].
        $schedules = Schedule::whereRaw(
                "STR_TO_DATE(CONCAT(scheduled_date, ' ', scheduled_time), '%Y-%m-%d %H:%i:%s') BETWEEN ? AND ?",
                [$now->toDateTimeString(), $windowEnd->toDateTimeString()]
            )
            ->where('status', 'confirmed')
            ->where('reminder_sent', false)
            ->with(['customer', 'supplier.user'])
            ->get();

        foreach ($schedules as $schedule) {
            try {
                if ($schedule->customer) {
                    $schedule->customer->notify(new ScheduleReminderNotification($schedule));
                }
                if ($schedule->supplier?->user) {
                    $schedule->supplier->user->notify(new ScheduleReminderNotification($schedule));
                }

                $schedule->update(['reminder_sent' => true]);
            } catch (\Throwable $e) {
                Log::error('Schedule reminder failed', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($schedules->count() > 0) {
            Log::info("Sent {$schedules->count()} schedule reminders");
        }
    }
}
