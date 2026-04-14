<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Models\AuditLog;
use App\Notifications\OrderCompletedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandleOrderCompleted implements ShouldQueue
{
    public function handle(OrderCompleted $event): void
    {
        $order = $event->order;

        AuditLog::create([
            'user_id' => $order->supplier->user_id,
            'action' => 'order_completed',
            'entity_type' => 'orders',
            'entity_id' => $order->id,
            'new_value' => ['order_number' => $order->order_number],
        ]);

        // Notify customer
        try {
            $order->loadMissing(['customer']);
            $order->customer->notify(new OrderCompletedNotification($order));
        } catch (\Throwable $e) {
            Log::warning('Failed to send OrderCompleted notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
