<?php

namespace App\Listeners;

use App\Events\MilestoneReleased;
use App\Models\AuditLog;
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

            AuditLog::create([
                'user_id' => $supplier?->user_id,
                'action' => 'milestone_released',
                'entity_type' => 'payment_milestones',
                'entity_id' => $milestone->id,
                'new_value' => [
                    'milestone_sequence' => $milestone->sequence,
                    'order_id' => $milestone->order_id,
                    'amount' => (float) $milestone->amount,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Milestone released notification failed', ['error' => $e->getMessage()]);
        }
    }
}
