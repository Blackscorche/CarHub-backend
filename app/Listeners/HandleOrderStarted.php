<?php

namespace App\Listeners;

use App\Events\OrderStarted;
use App\Models\AuditLog;
use App\Notifications\OrderStartedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandleOrderStarted implements ShouldQueue
{
    public function handle(OrderStarted $event): void
    {
        $order = $event->order;

        AuditLog::create([
            'user_id' => $order->supplier->user_id,
            'action' => 'order_started',
            'entity_type' => 'orders',
            'entity_id' => $order->id,
            'new_value' => ['order_number' => $order->order_number],
        ]);

        // Notify customer
        try {
            $order->loadMissing(['customer']);
            $order->customer->notify(new OrderStartedNotification($order));
        } catch (\Throwable $e) {
            Log::warning('Failed to send OrderStarted notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
