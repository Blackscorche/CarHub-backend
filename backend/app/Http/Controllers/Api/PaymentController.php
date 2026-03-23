<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PaymentService $paymentService,
    ) {}

    /**
     * Process checkout — creates Pix QR or card charge.
     */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|uuid|exists:orders,id',
            'payment_method' => 'required|in:pix,credit_card,debit_card',
            'card_token' => 'required_if:payment_method,credit_card,debit_card|string',
            'installments' => 'nullable|integer|min:1|max:12',
            'type' => 'nullable|in:full,partial,remaining',
        ]);

        $user = $request->user();
        $order = Order::with(['customer', 'supplier'])->findOrFail($request->input('order_id'));

        if ($order->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }

        // Prevent duplicate full payments
        $type = $request->input('type', 'full');
        $existingPayment = Payment::where('order_id', $order->id)
            ->where('type', $type)
            ->whereIn('status', ['pending', 'held', 'released'])
            ->first();

        if ($existingPayment && $type === 'full') {
            return $this->error('Já existe um pagamento pendente para este pedido.', 422);
        }

        try {
            $method = $request->input('payment_method');

            if ($method === 'pix') {
                $result = $this->paymentService->createPixPayment($order, $type);
            } else {
                $result = $this->paymentService->createCardPayment(
                    $order,
                    $request->input('card_token'),
                    $type,
                    $request->input('installments', 1)
                );
            }

            return $this->success($result, 'Pagamento iniciado.');
        } catch (\Throwable $e) {
            Log::error('Checkout error', ['error' => $e->getMessage(), 'order_id' => $order->id]);
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Mercado Pago webhook endpoint.
     */
    public function webhook(Request $request): JsonResponse
    {
        // Verify webhook signature
        $signature = $request->header('x-signature');
        $requestId = $request->header('x-request-id');

        if ($signature && $requestId) {
            $webhookSecret = config('services.mercadopago.webhook_secret', '');
            if ($webhookSecret) {
                $dataId = $request->input('data.id', '');
                $manifest = "id:{$dataId};request-id:{$requestId};";
                $expectedHmac = hash_hmac('sha256', $manifest, $webhookSecret);

                // Extract ts and v1 from signature
                $parts = collect(explode(',', $signature))->mapWithKeys(function ($part) {
                    [$key, $value] = explode('=', $part, 2);
                    return [trim($key) => trim($value)];
                });

                if (isset($parts['v1']) && ! hash_equals($parts['v1'], $expectedHmac)) {
                    Log::warning('Invalid webhook signature', ['signature' => $signature]);
                    return response()->json(['status' => 'invalid_signature'], 401);
                }
            }
        }

        try {
            $this->paymentService->handleWebhook($request->all());
            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            Log::error('Webhook processing error', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Get payments for a specific order.
     */
    public function orderPayments(Request $request, Order $order): JsonResponse
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

        $payments = Payment::where('order_id', $order->id)->orderByDesc('created_at')->get();

        return $this->success($payments);
    }

    /**
     * Get supplier balance (held vs available).
     */
    public function supplierBalance(Request $request): JsonResponse
    {
        $supplier = $request->user()->supplier;
        if (! $supplier) {
            return $this->error('Fornecedor não encontrado.', 404);
        }

        $balance = $this->paymentService->getSupplierBalance($supplier->id);

        return $this->success($balance);
    }

    /**
     * Get supplier transaction history.
     */
    public function supplierTransactions(Request $request): JsonResponse
    {
        $supplier = $request->user()->supplier;
        if (! $supplier) {
            return $this->error('Fornecedor não encontrado.', 404);
        }

        $transactions = $this->paymentService->getSupplierTransactions(
            $supplier->id,
            $request->input('limit', 20)
        );

        return $this->success($transactions);
    }

    /**
     * Check payment status (for polling from mobile).
     */
    public function status(Request $request, Payment $payment): JsonResponse
    {
        $user = $request->user();

        if ($payment->payer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }

        return $this->success([
            'payment_id' => $payment->id,
            'status' => $payment->status,
            'amount' => $payment->amount,
            'method' => $payment->method,
            'created_at' => $payment->created_at,
        ]);
    }
}
