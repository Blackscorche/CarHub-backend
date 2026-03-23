<?php

namespace App\Jobs;

use App\Models\Payment;
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

    public function handle(PaymentService $paymentService): void
    {
        $payments = Payment::where('status', 'held')
            ->whereNotNull('hold_until')
            ->where('hold_until', '<', now())
            ->get();

        foreach ($payments as $payment) {
            try {
                $paymentService->releasePayment($payment);
                Log::info('Held payment auto-released', ['payment_id' => $payment->id]);
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
