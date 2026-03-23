<?php

namespace App\Jobs;

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
        $tomorrow = now()->addDay()->toDateString();

        $schedules = Schedule::where('scheduled_date', $tomorrow)
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
