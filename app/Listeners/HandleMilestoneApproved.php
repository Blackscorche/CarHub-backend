<?php

namespace App\Listeners;

use App\Events\MilestoneApproved;
use App\Models\AuditLog;
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

            AuditLog::create([
                'user_id' => $milestone->order->customer_id,
                'action' => 'milestone_approved',
                'entity_type' => 'payment_milestones',
                'entity_id' => $milestone->id,
                'new_value' => [
                    'milestone_sequence' => $milestone->sequence,
                    'order_id' => $milestone->order_id,
                    'amount' => (float) $milestone->amount,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Milestone approved notification failed', ['error' => $e->getMessage()]);
        }
    }
}
