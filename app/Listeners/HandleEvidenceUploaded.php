<?php

namespace App\Listeners;

use App\Events\EvidenceUploaded;
use App\Models\AuditLog;
use App\Notifications\MilestoneEvidenceNotification;
use Illuminate\Support\Facades\Log;

class HandleEvidenceUploaded
{
    public function handle(EvidenceUploaded $event): void
    {
        try {
            $milestone = $event->milestone;
            $milestone->loadMissing('order.customer');

            $customer = $milestone->order->customer;
            if ($customer) {
                $customer->notify(new MilestoneEvidenceNotification($milestone));
            }

            AuditLog::create([
                'user_id' => $milestone->order->supplier?->user_id,
                'action' => 'milestone_evidence_uploaded',
                'entity_type' => 'payment_milestones',
                'entity_id' => $milestone->id,
                'new_value' => [
                    'milestone_sequence' => $milestone->sequence,
                    'order_id' => $milestone->order_id,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Evidence uploaded notification failed', ['error' => $e->getMessage()]);
        }
    }
}
