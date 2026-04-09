<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;

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
    }
}
