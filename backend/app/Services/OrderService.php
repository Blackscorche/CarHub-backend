<?php

namespace App\Services;

use App\Events\OrderAccepted;
use App\Events\OrderCancelled;
use App\Events\OrderCompleted;
use App\Events\OrderConfirmed;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Events\OrderRejected;
use App\Events\OrderStarted;
use App\Models\CatalogItem;
use App\Models\Order;
use App\Models\PlatformConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        protected CouponService $couponService,
        protected CashbackService $cashbackService,
    ) {}

    public function createDirectOrder(array $data, string $customerId): Order
    {
        return DB::transaction(function () use ($data, $customerId) {
            $subtotal = 0;
            $orderItems = [];

            foreach ($data['items'] as $itemData) {
                $catalogItem = CatalogItem::where('id', $itemData['catalog_item_id'])
                    ->where('supplier_id', $data['supplier_id'])
                    ->where('is_active', true)
                    ->firstOrFail();

                if ($catalogItem->price_type === 'quote_required') {
                    throw new \InvalidArgumentException(
                        "O item \"{$catalogItem->name}\" requer orçamento."
                    );
                }

                $unitPrice = $catalogItem->price;
                $totalPrice = round($unitPrice * $itemData['quantity'], 2);
                $subtotal += $totalPrice;

                $orderItems[] = [
                    'catalog_item_id' => $catalogItem->id,
                    'name' => $catalogItem->name,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ];
            }

            // Commission rate from platform config or category override
            $commissionRate = $this->getCommissionRate($data['supplier_id']);
            $platformFee = round($subtotal * ($commissionRate / 100), 2);

            // Apply coupon discount
            $discount = 0;
            if (! empty($data['coupon_code'])) {
                $couponResult = $this->couponService->validate(
                    $data['coupon_code'],
                    $subtotal,
                    $data['supplier_id']
                );
                if ($couponResult['valid']) {
                    $discount = $couponResult['discount'];
                }
            }

            // Apply cashback
            $cashbackUsed = 0;
            if (! empty($data['use_cashback']) && $data['use_cashback']) {
                $cashbackUsed = $this->cashbackService->getAvailableBalance($customerId);
                $maxCashback = $subtotal - $discount;
                $cashbackUsed = min($cashbackUsed, $maxCashback);
            }

            $total = max(0, $subtotal - $discount - $cashbackUsed);

            // Generate sequential order number
            $todayPrefix = 'ORD-' . date('Ymd') . '-';
            $lastOrder = Order::where('order_number', 'like', $todayPrefix . '%')
                ->orderByDesc('order_number')
                ->first();
            $seq = 1;
            if ($lastOrder) {
                $lastSeq = (int) Str::afterLast($lastOrder->order_number, '-');
                $seq = $lastSeq + 1;
            }
            $orderNumber = $todayPrefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

            $order = Order::create([
                'order_number' => $orderNumber,
                'customer_id' => $customerId,
                'supplier_id' => $data['supplier_id'],
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'type' => 'direct',
                'status' => 'pending',
                'subtotal' => $subtotal,
                'platform_fee' => $platformFee,
                'total' => $total,
                'commission_rate' => $commissionRate,
                'payment_method' => $data['payment_method'] ?? null,
                'delivery_type' => $data['delivery_type'],
                'delivery_address_id' => $data['delivery_address_id'] ?? null,
                'confirmation_code' => strtoupper(Str::random(6)),
                'notes' => $data['notes'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
            ]);

            foreach ($orderItems as $item) {
                $order->items()->create($item);
            }

            // Debit cashback if used
            if ($cashbackUsed > 0) {
                $this->cashbackService->debit($customerId, $order->id, $cashbackUsed);
            }

            // Increment coupon usage
            if (! empty($data['coupon_code']) && $discount > 0) {
                $this->couponService->markUsed($data['coupon_code']);
            }

            $order->load('items');

            event(new OrderCreated($order));

            return $order;
        });
    }

    public function createQuoteOrder(array $data, string $customerId): Order
    {
        return DB::transaction(function () use ($data, $customerId) {
            $todayPrefix = 'ORD-' . date('Ymd') . '-';
            $lastOrder = Order::where('order_number', 'like', $todayPrefix . '%')
                ->orderByDesc('order_number')
                ->first();
            $seq = $lastOrder ? ((int) Str::afterLast($lastOrder->order_number, '-')) + 1 : 1;
            $orderNumber = $todayPrefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

            $order = Order::create([
                'order_number' => $orderNumber,
                'customer_id' => $customerId,
                'supplier_id' => $data['supplier_id'],
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'type' => 'quote',
                'status' => 'awaiting_quote',
                'subtotal' => 0,
                'platform_fee' => 0,
                'total' => 0,
                'commission_rate' => $this->getCommissionRate($data['supplier_id']),
                'payment_method' => $data['payment_method'] ?? 'pix',
                'delivery_type' => $data['delivery_type'] ?? 'pickup',
                'delivery_address_id' => $data['delivery_address_id'] ?? null,
                'confirmation_code' => strtoupper(Str::random(6)),
                'notes' => $data['notes'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
            ]);

            event(new OrderCreated($order));

            return $order;
        });
    }

    public function acceptOrder(Order $order): Order
    {
        $this->ensureStatus($order, ['pending', 'created']);

        $order->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        event(new OrderAccepted($order));

        return $order->fresh();
    }

    public function rejectOrder(Order $order, string $reason): Order
    {
        $this->ensureStatus($order, ['pending', 'created']);

        $order->update([
            'status' => 'rejected',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        event(new OrderRejected($order));

        return $order->fresh();
    }

    public function payOrder(Order $order): Order
    {
        $this->ensureStatus($order, ['accepted']);

        $order->update(['status' => 'paid']);

        event(new OrderPaid($order));

        return $order->fresh();
    }

    public function startOrder(Order $order): Order
    {
        $this->ensureStatus($order, ['paid']);

        $order->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        event(new OrderStarted($order));

        return $order->fresh();
    }

    public function completeOrder(Order $order): Order
    {
        $this->ensureStatus($order, ['in_progress']);

        $order->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        event(new OrderCompleted($order));

        return $order->fresh();
    }

    public function confirmOrder(Order $order, string $code): Order
    {
        $this->ensureStatus($order, ['completed', 'delivered']);

        if (strtoupper($code) !== strtoupper($order->confirmation_code)) {
            throw new \InvalidArgumentException('Código de confirmação inválido.');
        }

        $order->update(['status' => 'confirmed']);

        event(new OrderConfirmed($order));

        return $order->fresh();
    }

    public function cancelOrder(Order $order, string $reason, string $cancelledBy): Order
    {
        $cancellable = ['pending', 'created', 'paid', 'awaiting_quote', 'quote_sent', 'accepted'];
        $this->ensureStatus($order, $cancellable);

        $order->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        event(new OrderCancelled($order, $cancelledBy));

        return $order->fresh();
    }

    protected function getCommissionRate(string $supplierId): float
    {
        $config = PlatformConfig::where('key', 'commission_rate')->first();

        return $config ? (float) ($config->value['default'] ?? $config->value) : 15.00;
    }

    protected function ensureStatus(Order $order, array $allowed): void
    {
        if (! in_array($order->status, $allowed)) {
            throw new \InvalidArgumentException(
                "Ação não permitida para o status atual: {$order->status}"
            );
        }
    }
}
