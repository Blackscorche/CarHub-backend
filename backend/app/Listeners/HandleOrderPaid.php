<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Models\AuditLog;
use App\Notifications\OrderPaidNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandleOrderPaid implements ShouldQueue
{
    public function handle(OrderPaid $event): void
    {
        $order = $event->order;

        AuditLog::create([
            'user_id' => $order->customer_id,
            'action' => 'order_paid',
            'entity_type' => 'orders',
            'entity_id' => $order->id,
            'new_value' => ['order_number' => $order->order_number],
        ]);

        // Notify supplier that payment is complete
        try {
            $order->supplier->user->notify(new OrderPaidNotification($order));
        } catch (\Throwable $e) {
            Log::warning('Failed to send OrderPaid notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Order paid', ['order_id' => $order->id]);
    }
}
