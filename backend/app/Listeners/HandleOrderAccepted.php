<?php

namespace App\Listeners;

use App\Events\OrderAccepted;
use App\Models\AuditLog;
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

        Log::info('Order accepted', ['order_id' => $order->id]);
    }
}
