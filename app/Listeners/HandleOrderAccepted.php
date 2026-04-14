<?php

namespace App\Listeners;

use App\Events\OrderAccepted;
use App\Models\AuditLog;
use App\Notifications\OrderAcceptedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandleOrderAccepted implements ShouldQueue
{
    public function handle(OrderAccepted $event): void
    {
        $order = $event->order;

        AuditLog::create([
            'user_id' => $order->supplier->user_id,
            'action' => 'order_accepted',
            'entity_type' => 'orders',
            'entity_id' => $order->id,
            'new_value' => ['order_number' => $order->order_number],
        ]);

        // Notify customer
        try {
            $order->loadMissing(['customer']);
            $order->customer->notify(new OrderAcceptedNotification($order));
        } catch (\Throwable $e) {
            Log::warning('Failed to send OrderAccepted notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Order accepted', ['order_id' => $order->id]);
    }
}
