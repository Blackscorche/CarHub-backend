<?php

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Models\AuditLog;
use App\Notifications\OrderCancelledNotification;
use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandleOrderCancelled implements ShouldQueue
{
    public function __construct(protected PaymentService $paymentService) {}

    public function handle(OrderCancelled $event): void
    {
        $order = $event->order;

        // Refund all held payments
        $heldPayments = $order->payments()->where('status', 'held')->get();
        foreach ($heldPayments as $payment) {
            try {
                $this->paymentService->refund($payment);
            } catch (\Throwable $e) {
                Log::error('Cancellation refund failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        AuditLog::create([
            'user_id' => $order->customer_id,
            'action' => 'order_cancelled',
            'entity_type' => 'orders',
            'entity_id' => $order->id,
            'new_value' => [
                'order_number' => $order->order_number,
                'cancelled_by' => $event->cancelledBy,
                'reason' => $order->cancellation_reason,
            ],
        ]);

        // Notify both customer and supplier's user
        try {
            $order->loadMissing(['customer', 'supplier.user']);
            $order->customer->notify(new OrderCancelledNotification($order));
            $order->supplier->user->notify(new OrderCancelledNotification($order));
        } catch (\Throwable $e) {
            Log::warning('Failed to send OrderCancelled notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Order cancelled', ['order_id' => $order->id, 'by' => $event->cancelledBy]);
    }
}
