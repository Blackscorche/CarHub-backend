<?php

namespace App\Jobs;

use App\Services\CashbackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireCashback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CashbackService $cashbackService): void
    {
        $count = $cashbackService->expireOldCashback();

        if ($count > 0) {
            Log::info("Expired {$count} cashback transactions");
        }
    }
}
