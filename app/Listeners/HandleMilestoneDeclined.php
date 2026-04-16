<?php

namespace App\Listeners;

use App\Events\MilestoneDeclined;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

class HandleMilestoneDeclined
{
    public function handle(MilestoneDeclined $event): void
    {
        try {
            $milestone = $event->milestone;
            $milestone->loadMissing('order');

            AuditLog::create([
                'user_id' => $milestone->order?->customer_id,
                'action' => 'milestone_declined',
                'entity_type' => 'payment_milestones',
                'entity_id' => $milestone->id,
                'new_value' => [
                    'milestone_sequence' => $milestone->sequence,
                    'order_id' => $milestone->order_id,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Milestone declined audit failed', ['error' => $e->getMessage()]);
        }
    }
}
