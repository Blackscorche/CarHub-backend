<?php

namespace App\Jobs;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpirePixPayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $expired = Payment::where('method', 'pix')
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(15))
            ->get();

        foreach ($expired as $payment) {
            $payment->update(['status' => 'failed']);

            // If no other successful payment exists, revert order status
            $order = $payment->order;
            $hasOtherPayment = $order->payments()
                ->whereIn('status', ['held', 'released', 'confirmed'])
                ->exists();

            if (! $hasOtherPayment && $order->status === 'created') {
                Log::info('Pix payment expired, order remains created', [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                ]);
            }
        }

        if ($expired->count() > 0) {
            Log::info("Expired {$expired->count()} Pix payments");
        }
    }
}
