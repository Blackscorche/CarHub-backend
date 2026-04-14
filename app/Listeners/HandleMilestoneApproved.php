<?php

namespace App\Listeners;

use App\Events\MilestoneApproved;
use App\Notifications\MilestoneApprovedNotification;
use Illuminate\Support\Facades\Log;

class HandleMilestoneApproved
{
    public function handle(MilestoneApproved $event): void
    {
        try {
            $milestone = $event->milestone;
            $milestone->loadMissing('order.supplier.user');

            $supplier = $milestone->order->supplier;
            if ($supplier?->user) {
                $supplier->user->notify(new MilestoneApprovedNotification($milestone));
            }
        } catch (\Throwable $e) {
            Log::warning('Milestone approved notification failed', ['error' => $e->getMessage()]);
        }
    }
}
