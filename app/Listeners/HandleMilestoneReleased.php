<?php

namespace App\Listeners;

use App\Events\MilestoneReleased;
use App\Notifications\MilestoneReleasedNotification;
use Illuminate\Support\Facades\Log;

class HandleMilestoneReleased
{
    public function handle(MilestoneReleased $event): void
    {
        try {
            $milestone = $event->milestone;
            $milestone->loadMissing('order.supplier.user');

            $supplier = $milestone->order->supplier;
            if ($supplier?->user) {
                $supplier->user->notify(new MilestoneReleasedNotification($milestone));
            }
        } catch (\Throwable $e) {
            Log::warning('Milestone released notification failed', ['error' => $e->getMessage()]);
        }
    }
}
