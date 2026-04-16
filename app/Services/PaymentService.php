<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PlatformConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected PagarmeClient $pagarme;

    public function __construct(PagarmeClient $pagarme)
    {
        $this->pagarme = $pagarme;
    }

    // ─── Pix Payment ─────────────────────────────────────────

    public function createPixPayment(Order $order, string $type = 'full'): array
    {
        $order->loadMissing(['items', 'customer', 'supplier']);

        $amount = $this->resolveAmount($order, $type);
        $split = $this->calculateSplit($order, $amount);
        $idempotencyKey = "pix-{$order->id}-{$type}";

        $payload = [
            'items' => $this->buildItems($order),
            'customer' => $this->buildCustomer($order),
            'payments' => [
                [
                    'payment_method' => 'pix',
                    'pix' => [
                        'expires_in' => 900, // 15 minutes
                    ],
                    'amount' => (int) round($amount * 100), // Pagar.me uses cents
                    'split' => $this->buildSplit($order, $split),
                ],
            ],
        ];

        $response = $this->pagarme->post('/orders', $payload, $idempotencyKey);

        $charge = $response['charges'][0] ?? [];
        $pixData = $charge['last_transaction'] ?? [];

        $payment = Payment::create([
            'order_id' => $order->id,
            'payer_id' => $order->customer_id,
            'amount' => $amount,
            'platform_fee' => $split['platform_fee'],
            'supplier_amount' => $split['supplier_amount'],
            'method' => 'pix',
            'type' => $type,
            'status' => 'pending',
            'pagarme_charge_id' => $charge['id'] ?? null,
            'gateway_response' => $response,
        ]);

        return [
            'payment_id' => $payment->id,
            'pix_code' => $pixData['qr_code'] ?? null,
            'qr_code_url' => $pixData['qr_code_url'] ?? null,
            'expires_at' => $pixData['expires_at'] ?? now()->addMinutes(15)->toIso8601String(),
            'status' => 'pending',
        ];
    }

    // ─── Card Payment ────────────────────────────────────────

    public function createCardPayment(Order $order, string $cardToken, string $type = 'full', int $installments = 1, string $cardType = 'credit_card'): array
    {
        $order->loadMissing(['items', 'customer', 'supplier']);

        $amount = $this->resolveAmount($order, $type);
        $split = $this->calculateSplit($order, $amount);
        $idempotencyKey = "card-{$order->id}-{$type}";

        $cardData = [
            'card_token' => $cardToken,
            'statement_descriptor' => 'CARHUB',
        ];
        if ($cardType === 'credit_card') {
            $cardData['installments'] = $installments;
        }

        $payload = [
            'items' => $this->buildItems($order),
            'customer' => $this->buildCustomer($order),
            'payments' => [
                [
                    'payment_method' => $cardType,
                    $cardType => $cardData,
                    'amount' => (int) round($amount * 100),
                    'split' => $this->buildSplit($order, $split),
                ],
            ],
        ];

        $response = $this->pagarme->post('/orders', $payload, $idempotencyKey);

        $charge = $response['charges'][0] ?? [];
        $status = $this->mapPagarmeStatus($charge['status'] ?? 'pending');

        $payment = Payment::create([
            'order_id' => $order->id,
            'payer_id' => $order->customer_id,
            'amount' => $amount,
            'platform_fee' => $split['platform_fee'],
            'supplier_amount' => $split['supplier_amount'],
            'method' => $cardType,
            'type' => $type,
            'status' => $status,
            'pagarme_charge_id' => $charge['id'] ?? null,
            'gateway_response' => $response,
            // hold_until is set only when the supplier marks the order completed
            // (the 48h countdown is "post-completion, pending customer confirmation").
            'hold_until' => null,
        ]);

        if ($status === 'held') {
            $this->updateOrderAfterPayment($order, $type);
        }

        return [
            'payment_id' => $payment->id,
            'status' => $status,
        ];
    }

    // ─── Webhook ─────────────────────────────────────────────

    public function handleWebhook(array $payload): void
    {
        $type = $payload['type'] ?? null;

        // Route transfer events to WithdrawalService
        if (str_starts_with($type ?? '', 'transfer.')) {
            app(WithdrawalService::class)->handleTransferWebhook($payload);
            return;
        }

        if ($type !== 'charge.paid' && $type !== 'charge.payment_failed') {
            return;
        }

        $chargeData = $payload['data'] ?? [];
        $chargeId = $chargeData['id'] ?? null;
        if (!$chargeId) {
            return;
        }

        $payment = Payment::where('pagarme_charge_id', $chargeId)->first();
        if (!$payment) {
            Log::warning('Payment not found for Pagar.me charge', ['charge_id' => $chargeId]);
            return;
        }

        $newStatus = $type === 'charge.paid' ? 'held' : 'failed';

        DB::transaction(function () use ($payment, $newStatus, $chargeData) {
            $payment->update([
                'status' => $newStatus,
                'gateway_response' => $chargeData,
                // hold_until is not set here; it is assigned when the supplier marks the order completed.
                'hold_until' => $payment->hold_until,
            ]);

            if ($newStatus === 'held') {
                $payment->loadMissing('order');
                $this->updateOrderAfterPayment($payment->order, $payment->type);
                event(new \App\Events\OrderPaid($payment->order));
            }
        });
    }

    // ─── Refund ──────────────────────────────────────────────

    public function refund(Payment $payment, ?float $amount = null): void
    {
        if (!$payment->pagarme_charge_id) {
            throw new \RuntimeException('Pagamento sem ID de cobrança no Pagar.me.');
        }

        $refundAmountCents = (int) round(($amount ?? $payment->amount) * 100);

        $this->pagarme->post("/charges/{$payment->pagarme_charge_id}/refunds", [
            'amount' => $refundAmountCents,
        ]);

        $payment->update([
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);
    }

    // ─── Manual Withdraw (for milestone releases) ────────────

    public function manualWithdraw(string $recipientId, float $amount): array
    {
        try {
            $response = $this->pagarme->post("/recipients/{$recipientId}/withdrawals", [
                'amount' => (int) round($amount * 100),
            ]);

            Log::info('Pagar.me manual withdrawal', [
                'recipient_id' => $recipientId,
                'amount' => $amount,
                'response_id' => $response['id'] ?? null,
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::error('Pagar.me manual withdrawal failed', [
                'recipient_id' => $recipientId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Erro ao realizar saque para o fornecedor.');
        }
    }

    // ─── Release Payment ─────────────────────────────────────

    public function releasePayment(Payment $payment): void
    {
        // Step 1: Mark payment as released (always succeeds)
        $payment->update([
            'status' => 'released',
            'released_at' => now(),
        ]);

        // Step 2: Trigger automatic withdrawal (separate, can fail and retry)
        $payment->loadMissing('order.supplier');
        $supplier = $payment->order->supplier;

        if ($supplier && $supplier->pagarme_recipient_id && $supplier->bankAccount) {
            try {
                $withdrawalService = app(WithdrawalService::class);
                $withdrawalService->requestWithdrawal(
                    $supplier,
                    (float) $payment->supplier_amount,
                    'automatic'
                );
            } catch (\Throwable $e) {
                // Withdrawal failed but release is done — RetryFailedWithdrawals job will handle retry
                Log::warning('Auto-withdrawal after release failed', [
                    'payment_id' => $payment->id,
                    'supplier_id' => $supplier->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    // ─── Supplier Balance ────────────────────────────────────

    public function getSupplierBalance(string $supplierId): array
    {
        $supplier = \App\Models\Supplier::findOrFail($supplierId);

        // Earliest upcoming release date across held payments for this supplier
        // (hold_until is set by OrderService::completeOrder when the countdown starts).
        $nextRelease = Payment::whereHas('order', fn ($q) => $q->where('supplier_id', $supplierId))
            ->where('status', 'held')
            ->whereNotNull('hold_until')
            ->min('hold_until');

        if ($supplier->pagarme_recipient_id) {
            try {
                $response = $this->pagarme->get("/recipients/{$supplier->pagarme_recipient_id}/balance");
                return [
                    'available' => ($response['available']['amount'] ?? 0) / 100,
                    'held' => ($response['waiting_funds']['amount'] ?? 0) / 100,
                    'total' => (($response['available']['amount'] ?? 0) + ($response['waiting_funds']['amount'] ?? 0)) / 100,
                    'next_release_at' => $nextRelease,
                ];
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch Pagar.me balance', ['error' => $e->getMessage()]);
            }
        }

        // Fallback to DB calculation
        $held = Payment::whereHas('order', fn ($q) => $q->where('supplier_id', $supplierId))
            ->where('status', 'held')
            ->sum('supplier_amount');

        $released = Payment::whereHas('order', fn ($q) => $q->where('supplier_id', $supplierId))
            ->where('status', 'released')
            ->sum('supplier_amount');

        return [
            'available' => round((float) $released, 2),
            'held' => round((float) $held, 2),
            'total' => round((float) $held + (float) $released, 2),
            'next_release_at' => $nextRelease,
        ];
    }

    public function getSupplierTransactions(string $supplierId, int $limit = 20): mixed
    {
        return Payment::whereHas('order', fn ($q) => $q->where('supplier_id', $supplierId))
            ->with('order:id,order_number,total')
            ->orderByDesc('created_at')
            ->paginate($limit);
    }

    // ─── Helpers ─────────────────────────────────────────────

    protected function buildItems(Order $order): array
    {
        return $order->items->map(fn ($item) => [
            'amount' => (int) round($item->unit_price * $item->quantity * 100),
            'description' => $item->name,
            'quantity' => $item->quantity,
        ])->toArray();
    }

    protected function buildCustomer(Order $order): array
    {
        $user = $order->customer;
        $cpf = $user->cpf ? preg_replace('/\D/', '', $user->cpf) : null;

        if (!$cpf) {
            throw new \RuntimeException('Cliente deve ter CPF cadastrado para realizar pagamento.');
        }

        return [
            'name' => $user->name,
            'email' => $user->email,
            'type' => 'individual',
            'document' => $cpf,
            'document_type' => 'CPF',
        ];
    }

    protected function buildSplit(Order $order, array $split): array
    {
        $supplier = $order->supplier;
        $platformRecipientId = config('services.pagarme.platform_recipient_id');

        if (!$supplier->pagarme_recipient_id) {
            throw new \RuntimeException('Fornecedor não possui cadastro no Pagar.me. Solicite que ele registre seus dados bancários.');
        }

        if (!$platformRecipientId) {
            throw new \RuntimeException('Platform recipient ID não configurado.');
        }

        return [
            // Supplier portion
            [
                'amount' => (int) round($split['supplier_amount'] * 100),
                'recipient_id' => $supplier->pagarme_recipient_id,
                'type' => 'flat',
                'options' => [
                    'liable' => true,
                    'charge_processing_fee' => true,
                    'charge_remainder_fee' => false,
                ],
            ],
            // Platform portion
            [
                'amount' => (int) round($split['platform_fee'] * 100),
                'recipient_id' => $platformRecipientId,
                'type' => 'flat',
                'options' => [
                    'liable' => false,
                    'charge_processing_fee' => false,
                    'charge_remainder_fee' => true,
                ],
            ],
        ];
    }

    protected function resolveAmount(Order $order, string $type): float
    {
        if ($type === 'partial') {
            $percent = $this->getPartialPaymentPercent();
            return round($order->total * ($percent / 100), 2);
        }

        if ($type === 'remaining') {
            $paid = Payment::where('order_id', $order->id)
                ->whereIn('status', ['held', 'released', 'confirmed'])
                ->sum('amount');
            return round($order->total - $paid, 2);
        }

        return (float) $order->total;
    }

    protected function calculateSplit(Order $order, float $amount): array
    {
        $commissionRate = (float) $order->commission_rate;
        $platformFee = round($amount * ($commissionRate / 100), 2);
        $supplierAmount = round($amount - $platformFee, 2);

        return [
            'platform_fee' => $platformFee,
            'supplier_amount' => $supplierAmount,
        ];
    }

    protected function updateOrderAfterPayment(Order $order, string $paymentType): void
    {
        $order = $order->fresh();

        if ($paymentType === 'partial') {
            $paid = Payment::where('order_id', $order->id)
                ->whereIn('status', ['held', 'released', 'confirmed'])
                ->sum('amount');

            $order->update([
                'status' => 'partially_paid',
                'partial_payment_amount' => $paid,
                'remaining_amount' => max(0, $order->total - $paid),
            ]);
        } elseif (in_array($paymentType, ['full', 'remaining'])) {
            $order->update([
                'status' => 'paid',
                'partial_payment_amount' => null,
                'remaining_amount' => 0,
            ]);
        }
    }

    protected function mapPagarmeStatus(string $status): string
    {
        return match ($status) {
            'paid' => 'held',
            'pending', 'processing' => 'pending',
            'failed', 'canceled' => 'failed',
            'refunded' => 'refunded',
            default => 'pending',
        };
    }

    protected function getHoldPeriodHours(): int
    {
        return Cache::remember('platform:hold_period_hours', 3600, function () {
            $config = PlatformConfig::where('key', 'hold_period_hours')->first();
            return $config ? (int) ($config->value['default'] ?? $config->value) : 48;
        });
    }

    protected function getPartialPaymentPercent(): int
    {
        return Cache::remember('platform:partial_payment_percent', 3600, function () {
            $config = PlatformConfig::where('key', 'partial_payment_percent')->first();
            return $config ? (int) ($config->value['default'] ?? $config->value) : 30;
        });
    }
}
