<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OrderService $orderService,
    ) {}

    /**
     * List orders for the authenticated user (customer or supplier).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Order::with(['items', 'supplier.user', 'customer', 'vehicle']);

        if ($user->role === 'customer') {
            $query->where('customer_id', $user->id);
        } elseif ($user->role === 'supplier') {
            $supplier = $user->supplier;
            if (! $supplier) {
                return $this->success([]);
            }
            $query->where('supplier_id', $supplier->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $orders = $query->orderByDesc('created_at')->paginate($request->input('limit', 20));

        return $this->success($orders);
    }

    /**
     * Show a single order.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'customer' && $order->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }

        if ($user->role === 'supplier') {
            $supplier = $user->supplier;
            if (! $supplier || $order->supplier_id !== $supplier->id) {
                return $this->forbidden('Acesso negado.');
            }
        }

        $order->load(['items', 'supplier.user', 'customer', 'vehicle', 'payments', 'review', 'quote']);

        return $this->success($order);
    }

    /**
     * Create a new direct order (customer only).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'supplier_id' => 'required|uuid|exists:suppliers,id',
            'vehicle_id' => 'nullable|uuid|exists:vehicles,id',
            'items' => 'required|array|min:1',
            'items.*.catalog_item_id' => 'required|uuid|exists:catalog_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'delivery_type' => 'required|in:pickup,delivery,on_site',
            'delivery_address_id' => 'nullable|uuid|exists:addresses,id',
            'payment_method' => 'nullable|in:pix,credit_card,debit_card',
            'coupon_code' => 'nullable|string|max:50',
            'use_cashback' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        try {
            $order = $this->orderService->createDirectOrder(
                $request->all(),
                $request->user()->id
            );

            return $this->created($order, 'Pedido criado com sucesso.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Supplier accepts an order.
     */
    public function accept(Request $request, Order $order): JsonResponse
    {
        $this->authorizeSupplier($request, $order);

        try {
            $order = $this->orderService->acceptOrder($order);
            return $this->success($order, 'Pedido aceito.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Supplier rejects an order.
     */
    public function reject(Request $request, Order $order): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);
        $this->authorizeSupplier($request, $order);

        try {
            $order = $this->orderService->rejectOrder($order, $request->input('reason'));
            return $this->success($order, 'Pedido rejeitado. Reembolso será processado.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Supplier starts working on an order.
     */
    public function start(Request $request, Order $order): JsonResponse
    {
        $this->authorizeSupplier($request, $order);

        try {
            $order = $this->orderService->startOrder($order);
            return $this->success($order, 'Trabalho iniciado.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Supplier completes work on an order.
     */
    public function complete(Request $request, Order $order): JsonResponse
    {
        $this->authorizeSupplier($request, $order);

        try {
            $order = $this->orderService->completeOrder($order);
            return $this->success($order, 'Trabalho concluído.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Customer confirms order with confirmation code.
     */
    public function confirm(Request $request, Order $order): JsonResponse
    {
        $request->validate(['confirmation_code' => 'required|string|size:6']);

        $user = $request->user();
        if ($order->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }

        try {
            $order = $this->orderService->confirmOrder($order, $request->input('confirmation_code'));
            return $this->success($order, 'Pedido confirmado. Pagamento liberado.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Customer pays for an accepted order (triggers MP Split Payment).
     */
    public function pay(Request $request, Order $order): JsonResponse
    {
        Log::info('Payment initialization requested', ['order_id' => $order->id, 'method' => $request->input('payment_method')]);
        $request->validate([
            'payment_method' => 'required|in:pix,credit_card,debit_card',
            'card_token' => 'required_if:payment_method,credit_card,debit_card|string',
            'installments' => 'nullable|integer|min:1|max:12',
        ]);

        $user = $request->user();
        if ($order->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }

        if (!in_array($order->status, ['accepted', 'partially_paid', 'quote_approved'])) {
            return $this->error('Pedido não pode ser pago neste status.', 422);
        }

        $order->update(['payment_method' => $request->input('payment_method')]);

        try {
            $paymentService = app(\App\Services\PaymentService::class);
            $method = $request->input('payment_method');

            if ($method === 'pix') {
                // BUG 8 FIX: Prevent creating duplicate PIX charges if one is already pending
                $existingPix = $order->payments()
                    ->where('method', 'pix')
                    ->where('status', 'pending')
                    ->latest()
                    ->first();

                $pixData = $existingPix
                    ? ($existingPix->gateway_response['charges'][0]['last_transaction'] ?? [])
                    : [];
                $hasValidQr = !empty($pixData['qr_code']);

                if ($existingPix && $hasValidQr) {
                    $result = [
                        'payment_id' => $existingPix->id,
                        'pix_code' => $pixData['qr_code'],
                        'qr_code_url' => $pixData['qr_code_url'] ?? null,
                        'expires_at' => $pixData['expires_at'] ?? now()->addMinutes(15)->toIso8601String(),
                        'status' => 'pending',
                    ];
                } else {
                    if ($existingPix) {
                        $existingPix->update(['status' => 'failed']);
                    }
                    $result = $paymentService->createPixPayment($order, 'full');
                }
            } else {
                // Card token comes pre-tokenized from the mobile app (via Pagar.me /tokens).
                // Raw card data never reaches this server — PCI compliant.
                $cardToken = $request->input('card_token');
                $cardType = $method === 'debit_card' ? 'debit_card' : 'credit_card';
                $installments = $cardType === 'debit_card' ? 1 : $request->input('installments', 1);

                $result = $paymentService->createCardPayment(
                    $order,
                    $cardToken,
                    'full',
                    $installments,
                    $cardType
                );
            }

            return $this->success($result, 'Pagamento iniciado.');
        } catch (\Throwable $e) {
            Log::error('Payment error', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Get latest payment status for an order (used for PIX polling).
     */
    public function paymentStatus(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        if ($order->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }

        $payment = $order->payments()->latest()->first();

        if (!$payment) {
            return $this->success(['status' => 'pending']);
        }

        return $this->success([
            'status' => $payment->status,
            'payment_id' => $payment->id,
            'method' => $payment->method,
        ]);
    }

    /**
     * Cancel an order (customer or supplier).
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $user = $request->user();

        // Authorization
        if ($user->role === 'customer' && $order->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }
        if ($user->role === 'supplier') {
            $supplier = $user->supplier;
            if (! $supplier || $order->supplier_id !== $supplier->id) {
                return $this->forbidden('Acesso negado.');
            }
        }

        try {
            $order = $this->orderService->cancelOrder(
                $order,
                $request->input('reason', 'Cancelado pelo usuário.'),
                $user->role
            );
            return $this->success($order, 'Pedido cancelado.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    protected function authorizeSupplier(Request $request, Order $order): void
    {
        $user = $request->user();
        $supplier = $user->supplier;

        if (! $supplier || $order->supplier_id !== $supplier->id) {
            abort(403, 'Acesso negado.');
        }
    }
}
