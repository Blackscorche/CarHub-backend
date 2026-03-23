<?php

namespace App\Listeners;

use App\Events\OrderStarted;
use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;

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
    }
}
