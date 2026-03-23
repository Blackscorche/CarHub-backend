<?php

namespace App\Jobs;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireQuotes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $expired = Quote::whereIn('status', ['pending', 'sent'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $quote) {
            $quote->update(['status' => 'expired']);

            $order = $quote->order;
            if ($order && in_array($order->status, ['awaiting_quote', 'quote_sent'])) {
                $order->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Orçamento expirado.',
                ]);
            }
        }

        if ($expired->count() > 0) {
            Log::info("Expired {$expired->count()} quotes");
        }
    }
}
