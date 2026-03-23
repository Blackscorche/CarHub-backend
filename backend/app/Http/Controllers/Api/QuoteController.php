<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Quote;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class QuoteController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OrderService $orderService,
        protected PaymentService $paymentService,
    ) {}

    /**
     * Create a quote request (customer).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'supplier_id' => 'required|uuid|exists:suppliers,id',
            'vehicle_id' => 'nullable|uuid|exists:vehicles,id',
            'description' => 'required|string|min:20|max:2000',
            'media' => 'nullable|array|max:6',
            'media.*' => 'file|mimes:jpg,jpeg,png,mp4|max:10240',
            'delivery_type' => 'nullable|in:pickup,delivery,on_site',
            'payment_method' => 'nullable|in:pix,credit_card,debit_card',
        ]);

        $user = $request->user();

        // Create order with quote type
        $order = $this->orderService->createQuoteOrder([
            'supplier_id' => $request->input('supplier_id'),
            'vehicle_id' => $request->input('vehicle_id'),
            'delivery_type' => $request->input('delivery_type', 'pickup'),
            'payment_method' => $request->input('payment_method', 'pix'),
            'notes' => $request->input('description'),
        ], $user->id);

        // Upload media files
        $mediaUrls = [];
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $path = $file->store('quotes/' . $order->id, 'public');
                $mediaUrls[] = Storage::url($path);
            }
        }

        // Create quote record
        $quote = Quote::create([
            'order_id' => $order->id,
            'supplier_id' => $request->input('supplier_id'),
            'customer_id' => $user->id,
            'description' => $request->input('description'),
            'media_urls' => $mediaUrls,
            'status' => 'pending',
            'partial_payment_percent' => 30,
            'expires_at' => now()->addHours(48),
        ]);

        $order->load('quote');

        return $this->created(['order' => $order, 'quote' => $quote], 'Orçamento solicitado com sucesso.');
    }

    /**
     * Supplier responds to a quote with pricing.
     */
    public function respond(Request $request, Quote $quote): JsonResponse
    {
        $request->validate([
            'initial_price' => 'required|numeric|min:0.01',
            'estimated_duration' => 'nullable|string|max:100',
            'supplier_notes' => 'nullable|string|max:1000',
            'partial_payment_percent' => 'nullable|integer|min:0|max:100',
        ]);

        $user = $request->user();
        $supplier = $user->supplier;

        if (! $supplier || $quote->supplier_id !== $supplier->id) {
            return $this->forbidden('Acesso negado.');
        }

        if ($quote->status !== 'pending') {
            return $this->error('Este orçamento já foi respondido.', 422);
        }

        $initialPrice = $request->input('initial_price');
        $commissionRate = (float) $quote->order->commission_rate;
        $platformFee = round($initialPrice * ($commissionRate / 100), 2);

        $quote->update([
            'initial_price' => $initialPrice,
            'estimated_duration' => $request->input('estimated_duration'),
            'supplier_notes' => $request->input('supplier_notes'),
            'partial_payment_percent' => $request->input('partial_payment_percent', 30),
            'status' => 'sent',
            'expires_at' => now()->addHours(24),
        ]);

        // Update order with pricing
        $quote->order->update([
            'status' => 'quote_sent',
            'subtotal' => $initialPrice,
            'platform_fee' => $platformFee,
            'total' => $initialPrice,
        ]);

        return $this->success($quote->fresh(), 'Orçamento enviado.');
    }

    /**
     * Customer approves a quote — triggers partial payment.
     */
    public function approve(Request $request, Quote $quote): JsonResponse
    {
        $user = $request->user();

        if ($quote->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }

        if ($quote->status !== 'sent') {
            return $this->error('Este orçamento não pode ser aprovado no status atual.', 422);
        }

        $quote->update(['status' => 'approved']);

        $order = $quote->order;
        $order->update([
            'status' => 'quote_approved',
            'payment_method' => $request->input('payment_method', $order->payment_method),
        ]);

        // Calculate partial payment amount
        $partialPercent = $quote->partial_payment_percent ?? 30;
        $partialAmount = round($order->total * ($partialPercent / 100), 2);

        $order->update([
            'partial_payment_amount' => $partialAmount,
            'remaining_amount' => round($order->total - $partialAmount, 2),
        ]);

        return $this->success([
            'quote' => $quote->fresh(),
            'order' => $order->fresh(),
            'partial_amount' => $partialAmount,
            'message' => 'Orçamento aprovado. Pague o sinal para confirmar.',
        ]);
    }

    /**
     * Supplier adjusts quote price after on-site inspection.
     */
    public function adjust(Request $request, Quote $quote): JsonResponse
    {
        $request->validate([
            'final_price' => 'required|numeric|min:0.01',
            'supplier_notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $supplier = $user->supplier;

        if (! $supplier || $quote->supplier_id !== $supplier->id) {
            return $this->forbidden('Acesso negado.');
        }

        if (! in_array($quote->status, ['approved', 'sent'])) {
            return $this->error('Este orçamento não pode ser ajustado.', 422);
        }

        $finalPrice = $request->input('final_price');
        $commissionRate = (float) $quote->order->commission_rate;
        $platformFee = round($finalPrice * ($commissionRate / 100), 2);

        $quote->update([
            'final_price' => $finalPrice,
            'supplier_notes' => $request->input('supplier_notes') ?? $quote->supplier_notes,
            'status' => 'adjusted',
        ]);

        // Recalculate order totals
        $order = $quote->order;
        $paid = $order->payments()->whereIn('status', ['held', 'released', 'confirmed'])->sum('amount');

        $order->update([
            'subtotal' => $finalPrice,
            'platform_fee' => $platformFee,
            'total' => $finalPrice,
            'remaining_amount' => max(0, $finalPrice - $paid),
        ]);

        return $this->success([
            'quote' => $quote->fresh(),
            'order' => $order->fresh(),
            'remaining_amount' => max(0, $finalPrice - $paid),
        ], 'Orçamento ajustado.');
    }

    /**
     * Customer approves final adjusted price — triggers remaining payment.
     */
    public function finalApprove(Request $request, Quote $quote): JsonResponse
    {
        $user = $request->user();

        if ($quote->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }

        if ($quote->status !== 'adjusted') {
            return $this->error('Este orçamento não pode ser aprovado no status atual.', 422);
        }

        $quote->update(['status' => 'final_approved']);

        $order = $quote->order;
        $paid = $order->payments()->whereIn('status', ['held', 'released', 'confirmed'])->sum('amount');
        $remaining = max(0, $order->total - $paid);

        return $this->success([
            'quote' => $quote->fresh(),
            'order' => $order->fresh(),
            'remaining_amount' => $remaining,
            'message' => 'Preço final aprovado. Pague o restante para continuar.',
        ]);
    }

    /**
     * Customer rejects a quote — refunds partial payment if any.
     */
    public function reject(Request $request, Quote $quote): JsonResponse
    {
        $user = $request->user();

        if ($quote->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }

        if (! in_array($quote->status, ['sent', 'adjusted'])) {
            return $this->error('Este orçamento não pode ser rejeitado.', 422);
        }

        DB::transaction(function () use ($quote) {
            $quote->update(['status' => 'rejected']);
            $quote->order->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            // Refund any held payments
            $heldPayments = $quote->order->payments()->where('status', 'held')->get();
            foreach ($heldPayments as $payment) {
                try {
                    $this->paymentService->refund($payment);
                } catch (\Throwable $e) {
                    \Log::error('Quote rejection refund failed', [
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return $this->success($quote->fresh(), 'Orçamento rejeitado.');
    }

    /**
     * List quotes for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Quote::with(['order', 'supplier.user', 'customer']);

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

        $quotes = $query->orderByDesc('created_at')->paginate($request->input('limit', 20));

        return $this->success($quotes);
    }

    /**
     * Show a single quote.
     */
    public function show(Request $request, Quote $quote): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'customer' && $quote->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }

        if ($user->role === 'supplier') {
            $supplier = $user->supplier;
            if (! $supplier || $quote->supplier_id !== $supplier->id) {
                return $this->forbidden('Acesso negado.');
            }
        }

        $quote->load(['order.items', 'order.payments', 'supplier.user', 'customer']);

        return $this->success($quote);
    }
}
