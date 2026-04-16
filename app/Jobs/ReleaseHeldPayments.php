<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Notifications\PaymentReleasedNotification;
use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReleaseHeldPayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Auto-release held payments whose 48h post-completion window has elapsed
     * without the customer confirming receipt. hold_until is set by
     * OrderService::completeOrder(), so only completed orders are in scope.
     */
    public function handle(PaymentService $paymentService): void
    {
        $payments = Payment::with('order')
            ->where('status', 'held')
            ->whereNotNull('hold_until')
            ->where('hold_until', '<', now())
            ->get();

        foreach ($payments as $payment) {
            try {
                $paymentService->releasePayment($payment);

                // Mark the order as delivered so the customer sees an "auto-confirmed" outcome.
                if ($payment->order && $payment->order->status === 'completed') {
                    $payment->order->update(['status' => 'delivered']);
                }

                // Notify supplier that the repasse was auto-released.
                $supplierUser = $payment->order?->supplier?->user;
                if ($supplierUser) {
                    $supplierUser->notify(new PaymentReleasedNotification($payment->fresh()));
                }

                Log::info('Held payment auto-released (48h no customer confirmation)', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                ]);
            } catch (\Throwable $e) {
                Log::error('Auto-release failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($payments->count() > 0) {
            Log::info("Released {$payments->count()} held payments");
        }
    }
}
