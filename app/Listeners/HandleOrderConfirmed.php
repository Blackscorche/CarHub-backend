<?php

namespace App\Listeners;

use App\Events\OrderConfirmed;
use App\Models\AuditLog;
use App\Services\CashbackService;
use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandleOrderConfirmed implements ShouldQueue
{
    public function __construct(
        protected PaymentService $paymentService,
        protected CashbackService $cashbackService,
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        $order = $event->order;

        // Release held payments to supplier
        $heldPayments = $order->payments()->where('status', 'held')->get();
        foreach ($heldPayments as $payment) {
            $this->paymentService->releasePayment($payment);
        }

        // Credit cashback to customer
        try {
            $cashbackAmount = $this->cashbackService->credit(
                $order->customer_id,
                $order->id,
                (float) $order->total
            );

            if ($cashbackAmount > 0) {
                Log::info('Cashback credited', [
                    'order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'amount' => $cashbackAmount,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Cashback credit failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        AuditLog::create([
            'user_id' => $order->customer_id,
            'action' => 'order_confirmed',
            'entity_type' => 'orders',
            'entity_id' => $order->id,
            'new_value' => ['order_number' => $order->order_number],
        ]);

        Log::info('Order confirmed, payments released', ['order_id' => $order->id]);
    }
}
