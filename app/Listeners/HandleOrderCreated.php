<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Models\AuditLog;
use App\Notifications\OrderCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandleOrderCreated implements ShouldQueue
{
    public function handle(OrderCreated $event): void
    {
        $order = $event->order;

        // Audit log
        AuditLog::create([
            'user_id' => $order->customer_id,
            'action' => 'order_created',
            'entity_type' => 'orders',
            'entity_id' => $order->id,
            'new_value' => [
                'order_number' => $order->order_number,
                'type' => $order->type,
                'total' => $order->total,
                'supplier_id' => $order->supplier_id,
            ],
        ]);

        // Notify supplier's user
        try {
            $order->loadMissing(['supplier.user']);
            $order->supplier->user->notify(new OrderCreatedNotification($order));
        } catch (\Throwable $e) {
            Log::warning('Failed to send OrderCreated notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Order created', ['order_id' => $order->id, 'order_number' => $order->order_number]);
    }
}
