<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PlatformConfig;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    protected string $mpAccessToken;
    protected string $mpBaseUrl = 'https://api.mercadopago.com';

    public function __construct()
    {
        $this->mpAccessToken = config('services.mercadopago.access_token', '');
    }

    public function createPixPayment(Order $order, string $type = 'full'): array
    {
        $amount = $this->resolveAmount($order, $type);
        $split = $this->calculateSplit($order, $amount);

        $idempotencyKey = 'pix-' . $order->id . '-' . $type . '-' . Str::random(8);

        $mpPayload = [
            'transaction_amount' => (float) $amount,
            'payment_method_id' => 'pix',
            'payer' => [
                'email' => $order->customer->email,
                'first_name' => $order->customer->name,
            ],
            'description' => "CarHub Pedido #{$order->order_number}",
            'external_reference' => $order->id,
            'notification_url' => config('services.mercadopago.webhook_url'),
        ];

        // Apply split if supplier has gateway account
        $supplier = $order->supplier;
        if ($supplier->payment_gateway_id) {
            $mpPayload['application_fee'] = $split['platform_fee'];
        }

        $response = Http::withToken($this->mpAccessToken)
            ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
            ->post("{$this->mpBaseUrl}/v1/payments", $mpPayload);

        if (! $response->successful()) {
            Log::error('MercadoPago Pix creation failed', [
                'order_id' => $order->id,
                'response' => $response->json(),
            ]);
            throw new \RuntimeException('Erro ao criar pagamento Pix.');
        }

        $mpData = $response->json();

        $payment = Payment::create([
            'order_id' => $order->id,
            'payer_id' => $order->customer_id,
            'amount' => $amount,
            'platform_fee' => $split['platform_fee'],
            'supplier_amount' => $split['supplier_amount'],
            'method' => 'pix',
            'type' => $type,
            'status' => 'pending',
            'gateway_transaction_id' => (string) $mpData['id'],
            'gateway_response' => $mpData,
        ]);

        return [
            'payment_id' => $payment->id,
            'gateway_id' => $mpData['id'],
            'qr_code' => $mpData['point_of_interaction']['transaction_data']['qr_code'] ?? null,
            'qr_code_base64' => $mpData['point_of_interaction']['transaction_data']['qr_code_base64'] ?? null,
            'expires_at' => $mpData['date_of_expiration'] ?? now()->addMinutes(15)->toIso8601String(),
        ];
    }

    public function createCardPayment(Order $order, string $cardToken, string $type = 'full', int $installments = 1): array
    {
        $amount = $this->resolveAmount($order, $type);
        $split = $this->calculateSplit($order, $amount);

        $idempotencyKey = 'card-' . $order->id . '-' . $type . '-' . Str::random(8);

        $mpPayload = [
            'transaction_amount' => (float) $amount,
            'token' => $cardToken,
            'installments' => $installments,
            'payer' => [
                'email' => $order->customer->email,
            ],
            'description' => "CarHub Pedido #{$order->order_number}",
            'external_reference' => $order->id,
            'notification_url' => config('services.mercadopago.webhook_url'),
        ];

        $supplier = $order->supplier;
        if ($supplier->payment_gateway_id) {
            $mpPayload['application_fee'] = $split['platform_fee'];
        }

        $response = Http::withToken($this->mpAccessToken)
            ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
            ->post("{$this->mpBaseUrl}/v1/payments", $mpPayload);

        if (! $response->successful()) {
            Log::error('MercadoPago Card payment failed', [
                'order_id' => $order->id,
                'response' => $response->json(),
            ]);
            throw new \RuntimeException('Erro ao processar pagamento com cartão.');
        }

        $mpData = $response->json();
        $status = $this->mapGatewayStatus($mpData['status'] ?? 'pending');

        $holdPeriod = $this->getHoldPeriodHours();
        $payment = Payment::create([
            'order_id' => $order->id,
            'payer_id' => $order->customer_id,
            'amount' => $amount,
            'platform_fee' => $split['platform_fee'],
            'supplier_amount' => $split['supplier_amount'],
            'method' => 'credit_card',
            'type' => $type,
            'status' => $status,
            'gateway_transaction_id' => (string) $mpData['id'],
            'gateway_response' => $mpData,
            'hold_until' => $status === 'held' ? now()->addHours($holdPeriod) : null,
        ]);

        if ($status === 'held') {
            $this->updateOrderAfterPayment($order, $type);
        }

        return [
            'payment_id' => $payment->id,
            'gateway_id' => $mpData['id'],
            'status' => $status,
        ];
    }

    public function handleWebhook(array $payload): void
    {
        $action = $payload['action'] ?? $payload['type'] ?? null;
        if ($action !== 'payment.updated' && $action !== 'payment') {
            return;
        }

        $gatewayId = $payload['data']['id'] ?? null;
        if (! $gatewayId) {
            return;
        }

        // Fetch payment details from Mercado Pago
        $response = Http::withToken($this->mpAccessToken)
            ->get("{$this->mpBaseUrl}/v1/payments/{$gatewayId}");

        if (! $response->successful()) {
            Log::error('MercadoPago webhook fetch failed', ['gateway_id' => $gatewayId]);
            return;
        }

        $mpData = $response->json();
        $payment = Payment::where('gateway_transaction_id', (string) $gatewayId)->first();

        if (! $payment) {
            Log::warning('Payment not found for gateway ID', ['gateway_id' => $gatewayId]);
            return;
        }

        $newStatus = $this->mapGatewayStatus($mpData['status']);

        DB::transaction(function () use ($payment, $newStatus, $mpData) {
            $payment->update([
                'status' => $newStatus,
                'gateway_response' => $mpData,
                'hold_until' => $newStatus === 'held' ? now()->addHours($this->getHoldPeriodHours()) : $payment->hold_until,
            ]);

            if ($newStatus === 'held') {
                $this->updateOrderAfterPayment($payment->order, $payment->type);
            }

            if ($newStatus === 'failed') {
                Log::info('Payment failed', ['payment_id' => $payment->id]);
            }
        });
    }

    public function refund(Payment $payment, ?float $amount = null): void
    {
        $refundAmount = $amount ?? $payment->amount;

        $response = Http::withToken($this->mpAccessToken)
            ->post("{$this->mpBaseUrl}/v1/payments/{$payment->gateway_transaction_id}/refunds", [
                'amount' => (float) $refundAmount,
            ]);

        if (! $response->successful()) {
            Log::error('MercadoPago refund failed', [
                'payment_id' => $payment->id,
                'response' => $response->json(),
            ]);
            throw new \RuntimeException('Erro ao processar reembolso.');
        }

        $payment->update([
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);
    }

    public function releasePayment(Payment $payment): void
    {
        $payment->update([
            'status' => 'released',
            'released_at' => now(),
        ]);
    }

    public function getSupplierBalance(string $supplierId): array
    {
        $held = Payment::whereHas('order', fn ($q) => $q->where('supplier_id', $supplierId))
            ->where('status', 'held')
            ->sum('supplier_amount');

        $released = Payment::whereHas('order', fn ($q) => $q->where('supplier_id', $supplierId))
            ->where('status', 'released')
            ->sum('supplier_amount');

        return [
            'held' => round((float) $held, 2),
            'available' => round((float) $released, 2),
            'total' => round((float) $held + (float) $released, 2),
        ];
    }

    public function getSupplierTransactions(string $supplierId, int $limit = 20): mixed
    {
        return Payment::whereHas('order', fn ($q) => $q->where('supplier_id', $supplierId))
            ->with('order:id,order_number,total')
            ->orderByDesc('created_at')
            ->paginate($limit);
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

    protected function mapGatewayStatus(string $mpStatus): string
    {
        return match ($mpStatus) {
            'approved' => 'held',
            'pending', 'in_process', 'authorized' => 'pending',
            'rejected', 'cancelled' => 'failed',
            'refunded' => 'refunded',
            default => 'pending',
        };
    }

    protected function getHoldPeriodHours(): int
    {
        $config = PlatformConfig::where('key', 'hold_period_hours')->first();
        return $config ? (int) ($config->value['default'] ?? $config->value) : 48;
    }

    protected function getPartialPaymentPercent(): int
    {
        $config = PlatformConfig::where('key', 'partial_payment_percent')->first();
        return $config ? (int) ($config->value['default'] ?? $config->value) : 30;
    }
}
