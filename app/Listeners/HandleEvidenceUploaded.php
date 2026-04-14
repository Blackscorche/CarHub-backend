<?php

namespace App\Listeners;

use App\Events\EvidenceUploaded;
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
        } catch (\Throwable $e) {
            Log::warning('Evidence uploaded notification failed', ['error' => $e->getMessage()]);
        }
    }
}
