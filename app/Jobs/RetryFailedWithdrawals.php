<?php

namespace App\Jobs;

use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryFailedWithdrawals implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(WithdrawalService $withdrawalService): void
    {
        $withdrawals = Withdrawal::retryable()->get();

        foreach ($withdrawals as $withdrawal) {
            try {
                $withdrawalService->retryFailedWithdrawal($withdrawal);
                Log::info('Withdrawal retried', ['withdrawal_id' => $withdrawal->id]);
            } catch (\Throwable $e) {
                Log::error('Withdrawal retry failed', [
                    'withdrawal_id' => $withdrawal->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($withdrawals->count() > 0) {
            Log::info("Retried {$withdrawals->count()} failed withdrawals");
        }
    }
}
