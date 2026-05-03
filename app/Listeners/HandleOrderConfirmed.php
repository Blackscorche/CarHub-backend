<?php

namespace App\Listeners;

use App\Events\OrderConfirmed;
use App\Models\AuditLog;
use App\Notifications\OrderConfirmedNotification;
use App\Notifications\PaymentReleasedNotification;
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

        $heldPayments = \App\Models\Payment::where('order_id', $order->id)
            ->where('status', 'held')
            ->get();

        // Wrap payment release, cashback and audit log in a transaction
        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $heldPayments) {
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
        });

        // Notifications outside transaction
        $order->loadMissing('supplier.user');
        $supplierUser = $order->supplier?->user;
        foreach ($heldPayments as $payment) {
            if ($supplierUser) {
                $supplierUser->notify(new PaymentReleasedNotification($payment->fresh()));
            }
        }

        // Notify supplier's user
        try {
            $order->loadMissing(['supplier.user']);
            $order->supplier->user->notify(new OrderConfirmedNotification($order));
        } catch (\Throwable $e) {
            Log::warning('Failed to send OrderConfirmed notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Order confirmed, payments released', ['order_id' => $order->id]);
    }
}
