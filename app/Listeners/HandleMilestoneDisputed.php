<?php

namespace App\Listeners;

use App\Events\MilestoneDisputed;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

class HandleMilestoneDisputed
{
    public function handle(MilestoneDisputed $event): void
    {
        try {
            $milestone = $event->milestone;
            $milestone->loadMissing('order');

            AuditLog::create([
                'user_id' => $milestone->order?->customer_id,
                'action' => 'milestone_disputed',
                'entity_type' => 'payment_milestones',
                'entity_id' => $milestone->id,
                'new_value' => [
                    'milestone_sequence' => $milestone->sequence,
                    'order_id' => $milestone->order_id,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Milestone disputed audit failed', ['error' => $e->getMessage()]);
        }
    }
}
