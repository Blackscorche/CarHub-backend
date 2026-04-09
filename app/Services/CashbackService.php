<?php

namespace App\Services;

use App\Models\CashbackTransaction;
use App\Models\CashbackWallet;
use App\Models\PlatformConfig;
use Illuminate\Support\Facades\DB;

class CashbackService
{
    public function getAvailableBalance(string $userId): float
    {
        $wallet = CashbackWallet::where('user_id', $userId)->first();
        if (! $wallet) {
            return 0;
        }

        return (float) $wallet->balance;
    }

    public function credit(string $userId, string $orderId, float $orderTotal): float
    {
        $rate = $this->getCashbackRate();
        if ($rate <= 0) {
            return 0;
        }

        $amount = round($orderTotal * ($rate / 100), 2);
        if ($amount <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($userId, $orderId, $amount) {
            $wallet = CashbackWallet::firstOrCreate(
                ['user_id' => $userId],
                ['balance' => 0]
            );

            $wallet->increment('balance', $amount);

            $expiryDays = $this->getCashbackExpiryDays();

            CashbackTransaction::create([
                'wallet_id' => $wallet->id,
                'order_id' => $orderId,
                'type' => 'credit',
                'amount' => $amount,
                'expires_at' => now()->addDays($expiryDays),
            ]);

            return $amount;
        });
    }

    public function debit(string $userId, string $orderId, float $amount): void
    {
        DB::transaction(function () use ($userId, $orderId, $amount) {
            $wallet = CashbackWallet::where('user_id', $userId)->lockForUpdate()->first();

            if (! $wallet || $wallet->balance < $amount) {
                throw new \InvalidArgumentException('Saldo de cashback insuficiente.');
            }

            $wallet->decrement('balance', $amount);

            CashbackTransaction::create([
                'wallet_id' => $wallet->id,
                'order_id' => $orderId,
                'type' => 'debit',
                'amount' => $amount,
            ]);
        });
    }

    public function expireOldCashback(): int
    {
        $expired = CashbackTransaction::where('type', 'credit')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expired as $transaction) {
            DB::transaction(function () use ($transaction, &$count) {
                $wallet = $transaction->wallet()->lockForUpdate()->first();
                if ($wallet && $wallet->balance >= $transaction->amount) {
                    $wallet->decrement('balance', $transaction->amount);
                    $transaction->delete();
                    $count++;
                }
            });
        }

        return $count;
    }

    public function getWalletWithTransactions(string $userId): array
    {
        $wallet = CashbackWallet::where('user_id', $userId)->first();

        if (! $wallet) {
            return ['balance' => 0, 'transactions' => []];
        }

        $transactions = CashbackTransaction::where('wallet_id', $wallet->id)
            ->with('order:id,order_number')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return [
            'balance' => (float) $wallet->balance,
            'transactions' => $transactions,
        ];
    }

    protected function getCashbackRate(): float
    {
        $config = PlatformConfig::where('key', 'cashback_rate')->first();
        return $config ? (float) ($config->value['default'] ?? $config->value) : 3.0;
    }

    protected function getCashbackExpiryDays(): int
    {
        $config = PlatformConfig::where('key', 'cashback_expiry_days')->first();
        return $config ? (int) ($config->value['default'] ?? $config->value) : 90;
    }
}
