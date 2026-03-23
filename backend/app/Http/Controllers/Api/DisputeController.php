<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Order;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DisputeController extends Controller
{
    use ApiResponse;

    public function __construct(protected PaymentService $paymentService) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|uuid|exists:orders,id',
            'category' => 'required|in:quality,incomplete,overcharge,no_show,damage,other',
            'description' => 'required|string|min:20|max:2000',
            'evidence' => 'nullable|array|max:5',
            'evidence.*' => 'file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $user = $request->user();
        $order = Order::findOrFail($request->input('order_id'));

        if ($order->customer_id !== $user->id && (! $user->supplier || $order->supplier_id !== $user->supplier->id)) {
            return $this->forbidden('Acesso negado.');
        }

        if ($order->dispute) {
            return $this->error('Já existe uma disputa para este pedido.', 422);
        }

        $allowedStatuses = ['in_progress', 'completed', 'delivered', 'confirmed'];
        if (! in_array($order->status, $allowedStatuses)) {
            return $this->error('Não é possível abrir disputa no status atual.', 422);
        }

        $evidenceUrls = [];
        if ($request->hasFile('evidence')) {
            foreach ($request->file('evidence') as $file) {
                $path = $file->store('disputes/' . $order->id, 'public');
                $evidenceUrls[] = Storage::url($path);
            }
        }

        $dispute = DB::transaction(function () use ($request, $user, $order, $evidenceUrls) {
            $dispute = Dispute::create([
                'order_id' => $order->id,
                'opened_by' => $user->id,
                'category' => $request->input('category'),
                'description' => $request->input('description'),
                'evidence_urls' => $evidenceUrls ?: null,
                'status' => 'open',
            ]);

            $order->update(['status' => 'disputed']);

            return $dispute;
        });

        return $this->created($dispute, 'Disputa aberta com sucesso.');
    }

    public function show(Request $request, Dispute $dispute): JsonResponse
    {
        $user = $request->user();
        $order = $dispute->order;

        if ($user->role === 'customer' && $order->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }
        if ($user->role === 'supplier') {
            $supplier = $user->supplier;
            if (! $supplier || $order->supplier_id !== $supplier->id) {
                return $this->forbidden('Acesso negado.');
            }
        }

        $dispute->load(['order.items', 'order.payments', 'openedBy:id,name']);

        return $this->success($dispute);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Dispute::with(['order:id,order_number,total,status', 'openedBy:id,name']);

        if ($user->role === 'customer') {
            $query->whereHas('order', fn ($q) => $q->where('customer_id', $user->id));
        } elseif ($user->role === 'supplier') {
            $supplier = $user->supplier;
            if (! $supplier) {
                return $this->success([]);
            }
            $query->whereHas('order', fn ($q) => $q->where('supplier_id', $supplier->id));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        return $this->success($query->orderByDesc('created_at')->paginate($request->input('limit', 20)));
    }

    public function resolve(Request $request, Dispute $dispute): JsonResponse
    {
        $request->validate([
            'resolution' => 'required|in:resolved_refund,resolved_partial,resolved_released,closed',
            'admin_notes' => 'nullable|string|max:1000',
            'refund_amount' => 'required_if:resolution,resolved_partial|nullable|numeric|min:0.01',
        ]);

        if ($dispute->status !== 'open' && $dispute->status !== 'under_review') {
            return $this->error('Disputa não pode ser resolvida no status atual.', 422);
        }

        DB::transaction(function () use ($request, $dispute) {
            $resolution = $request->input('resolution');
            $order = $dispute->order;

            $dispute->update([
                'status' => $resolution,
                'resolution' => $resolution,
                'admin_notes' => $request->input('admin_notes'),
                'resolved_at' => now(),
            ]);

            $heldPayments = $order->payments()->where('status', 'held')->get();

            if ($resolution === 'resolved_refund') {
                foreach ($heldPayments as $payment) {
                    $this->paymentService->refund($payment);
                }
                $order->update(['status' => 'refunded']);
            } elseif ($resolution === 'resolved_partial') {
                $refundAmount = $request->input('refund_amount');
                $firstPayment = $heldPayments->first();
                if ($firstPayment) {
                    $this->paymentService->refund($firstPayment, $refundAmount);
                }
                $order->update(['status' => 'refunded']);
            } elseif ($resolution === 'resolved_released') {
                foreach ($heldPayments as $payment) {
                    $this->paymentService->releasePayment($payment);
                }
                $order->update(['status' => 'confirmed']);
            } else {
                $order->update(['status' => 'confirmed']);
            }
        });

        return $this->success($dispute->fresh(), 'Disputa resolvida.');
    }
}
