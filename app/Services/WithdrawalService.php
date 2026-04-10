<?php

namespace App\Services;

use App\Models\PlatformConfig;
use App\Models\Supplier;
use App\Models\Withdrawal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawalService
{
    protected PagarmeClient $pagarme;

    public function __construct(PagarmeClient $pagarme)
    {
        $this->pagarme = $pagarme;
    }

    // ─── Request Withdrawal ─────────────────────────────────

    public function requestWithdrawal(Supplier $supplier, float $amount, string $type = 'manual'): Withdrawal
    {
        // Validate supplier has Pagar.me recipient
        if (!$supplier->pagarme_recipient_id) {
            throw new \RuntimeException('Cadastre seus dados bancários antes de solicitar um saque.');
        }

        // Validate supplier has bank account
        if (!$supplier->bankAccount) {
            throw new \RuntimeException('Cadastre sua conta bancária antes de solicitar um saque.');
        }

        // Validate minimum amount
        $minAmount = (float) PlatformConfig::getValue('min_withdrawal_amount', 10);
        if ($amount < $minAmount) {
            throw new \RuntimeException("Valor mínimo para saque é R$ " . number_format($minAmount, 2, ',', '.') . ".");
        }

        // Check for existing pending/processing withdrawal
        $activeWithdrawal = Withdrawal::where('supplier_id', $supplier->id)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        if ($activeWithdrawal) {
            throw new \RuntimeException('Você já possui um saque em andamento. Aguarde a conclusão.');
        }

        // Fetch real-time balance from Pagar.me
        $availableBalance = $this->getAvailableBalance($supplier);
        if ($amount > $availableBalance) {
            throw new \RuntimeException(
                'Saldo insuficiente. Disponível: R$ ' . number_format($availableBalance, 2, ',', '.') . '.'
            );
        }

        // Create withdrawal record and process
        return DB::transaction(function () use ($supplier, $amount, $type) {
            $withdrawal = Withdrawal::create([
                'supplier_id' => $supplier->id,
                'amount' => $amount,
                'fee' => 0,
                'net_amount' => $amount,
                'status' => 'pending',
                'type' => $type,
                'pagarme_recipient_id' => $supplier->pagarme_recipient_id,
                'requested_at' => now(),
            ]);

            $this->processWithdrawal($withdrawal);

            return $withdrawal->fresh();
        });
    }

    // ─── Process Withdrawal ─────────────────────────────────

    public function processWithdrawal(Withdrawal $withdrawal): void
    {
        $withdrawal->update([
            'status' => 'processing',
            'processed_at' => now(),
        ]);

        try {
            $response = $this->pagarme->post(
                "/recipients/{$withdrawal->pagarme_recipient_id}/withdrawals",
                ['amount' => (int) round($withdrawal->amount * 100)]
            );

            $fee = ($response['fee'] ?? 0) / 100;

            $withdrawal->update([
                'pagarme_withdrawal_id' => $response['id'] ?? null,
                'gateway_response' => $response,
                'fee' => $fee,
                'net_amount' => $withdrawal->amount - $fee,
            ]);

            Log::info('Withdrawal processed', [
                'withdrawal_id' => $withdrawal->id,
                'pagarme_id' => $response['id'] ?? null,
                'amount' => $withdrawal->amount,
            ]);
        } catch (\Throwable $e) {
            $withdrawal->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
                'failed_at' => now(),
                'retry_count' => $withdrawal->retry_count + 1,
            ]);

            Log::error('Withdrawal processing failed', [
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // ─── Webhook Handler ────────────────────────────────────

    public function handleTransferWebhook(array $payload): void
    {
        $type = $payload['type'] ?? null;
        $transferId = $payload['data']['id'] ?? null;

        if (!$transferId) {
            Log::warning('Transfer webhook missing transfer ID', $payload);
            return;
        }

        $withdrawal = Withdrawal::where('pagarme_withdrawal_id', $transferId)->first();

        if (!$withdrawal) {
            Log::warning('Transfer webhook: no matching withdrawal', ['transfer_id' => $transferId]);
            return;
        }

        // Prevent moving backwards (completed -> processing)
        $statusOrder = ['pending' => 0, 'processing' => 1, 'completed' => 2, 'failed' => 2];

        match ($type) {
            'transfer.created', 'transfer.processing' => $this->updateWithdrawalStatus(
                $withdrawal, 'processing', $statusOrder, $payload
            ),
            'transfer.paid' => $this->completeWithdrawal($withdrawal, $payload),
            'transfer.failed', 'transfer.canceled' => $this->failWithdrawal($withdrawal, $payload),
            default => Log::info('Unhandled transfer webhook type', ['type' => $type]),
        };
    }

    protected function completeWithdrawal(Withdrawal $withdrawal, array $payload): void
    {
        if ($withdrawal->status === 'completed') return;

        $fee = ($payload['data']['fee'] ?? 0) / 100;

        $withdrawal->update([
            'status' => 'completed',
            'completed_at' => now(),
            'fee' => $fee ?: $withdrawal->fee,
            'net_amount' => $withdrawal->amount - ($fee ?: $withdrawal->fee),
            'gateway_response' => $payload['data'] ?? $withdrawal->gateway_response,
        ]);

        // Notify supplier
        $withdrawal->loadMissing('supplier.user');
        $withdrawal->supplier->user->notify(
            new \App\Notifications\WithdrawalCompletedNotification($withdrawal)
        );

        Log::info('Withdrawal completed', ['withdrawal_id' => $withdrawal->id]);
    }

    protected function failWithdrawal(Withdrawal $withdrawal, array $payload): void
    {
        if ($withdrawal->status === 'completed') return;

        $reason = $payload['data']['failure_reason']
            ?? $payload['data']['last_transaction']['gateway_response']['errors'][0]['message']
            ?? 'Falha no processamento do saque.';

        $withdrawal->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason,
            'gateway_response' => $payload['data'] ?? $withdrawal->gateway_response,
            'retry_count' => $withdrawal->retry_count + 1,
        ]);

        // Notify supplier
        $withdrawal->loadMissing('supplier.user');
        $withdrawal->supplier->user->notify(
            new \App\Notifications\WithdrawalFailedNotification($withdrawal)
        );

        Log::error('Withdrawal failed via webhook', [
            'withdrawal_id' => $withdrawal->id,
            'reason' => $reason,
        ]);
    }

    protected function updateWithdrawalStatus(Withdrawal $withdrawal, string $status, array $statusOrder, array $payload): void
    {
        if (($statusOrder[$withdrawal->status] ?? 0) >= ($statusOrder[$status] ?? 0)) return;

        $withdrawal->update([
            'status' => $status,
            'gateway_response' => $payload['data'] ?? $withdrawal->gateway_response,
        ]);
    }

    // ─── Retry ──────────────────────────────────────────────

    public function retryFailedWithdrawal(Withdrawal $withdrawal): void
    {
        if ($withdrawal->retry_count >= 3) {
            throw new \RuntimeException('Número máximo de tentativas atingido.');
        }

        $withdrawal->update([
            'status' => 'pending',
            'failure_reason' => null,
            'failed_at' => null,
        ]);

        $this->processWithdrawal($withdrawal);
    }

    // ─── Queries ────────────────────────────────────────────

    public function getWithdrawals(string $supplierId, int $limit = 20): LengthAwarePaginator
    {
        return Withdrawal::where('supplier_id', $supplierId)
            ->orderByDesc('created_at')
            ->paginate($limit);
    }

    public function getAvailableBalance(Supplier $supplier): float
    {
        if (!$supplier->pagarme_recipient_id) return 0;

        try {
            $response = $this->pagarme->get("/recipients/{$supplier->pagarme_recipient_id}/balance");
            return ($response['available']['amount'] ?? 0) / 100;
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch balance for withdrawal', ['error' => $e->getMessage()]);
            return 0;
        }
    }
}
