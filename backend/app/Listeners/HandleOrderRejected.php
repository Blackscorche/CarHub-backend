<?php

namespace App\Listeners;

use App\Events\OrderRejected;
use App\Models\AuditLog;
use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandleOrderRejected implements ShouldQueue
{
    public function __construct(protected PaymentService $paymentService) {}

    public function handle(OrderRejected $event): void
    {
        $order = $event->order;

        // Auto-refund all held payments
        $heldPayments = $order->payments()->where('status', 'held')->get();
        foreach ($heldPayments as $payment) {
            try {
                $this->paymentService->refund($payment);
            } catch (\Throwable $e) {
                Log::error('Auto-refund failed on rejection', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        AuditLog::create([
            'user_id' => $order->supplier->user_id,
            'action' => 'order_rejected',
            'entity_type' => 'orders',
            'entity_id' => $order->id,
            'new_value' => [
                'order_number' => $order->order_number,
                'reason' => $order->cancellation_reason,
            ],
        ]);

        Log::info('Order rejected, refund triggered', ['order_id' => $order->id]);
    }
}
