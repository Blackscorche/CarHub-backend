<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentMilestone;
use App\Services\MilestoneService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    use ApiResponse;

    protected MilestoneService $milestoneService;

    public function __construct(MilestoneService $milestoneService)
    {
        $this->milestoneService = $milestoneService;
    }

    /**
     * Get milestone summary for an order.
     */
    public function index(string $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);
        $this->authorizeAccess($order);

        $summary = $this->milestoneService->getOrderSummary($order);

        return $this->success($summary);
    }

    /**
     * Customer pays a portion of the order.
     */
    public function pay(Request $request, string $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);

        if ($order->customer_id !== $request->user()->id) {
            return $this->error('Apenas o cliente pode realizar pagamentos.', 403);
        }

        if (!$order->isMilestone()) {
            return $this->error('Este pedido não utiliza pagamento por etapas.', 422);
        }

        if (!in_array($order->status, ['accepted', 'partially_paid', 'in_progress'])) {
            return $this->error('Pedido não está em estado válido para pagamento.', 422);
        }

        $request->validate([
            'percentage' => 'required|numeric|min:10|max:100',
            'payment_method' => 'required|in:pix,credit_card,debit_card',
            'card_token' => 'required_unless:payment_method,pix|string',
        ]);

        try {
            $result = $this->milestoneService->payMilestone(
                $order,
                (float) $request->percentage,
                $request->payment_method,
                $request->card_token,
            );

            return $this->success($result, 'Pagamento realizado com sucesso.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Supplier submits evidence for a milestone.
     */
    public function submitEvidence(Request $request, string $milestoneId): JsonResponse
    {
        $milestone = PaymentMilestone::findOrFail($milestoneId);
        $milestone->loadMissing('order.supplier');

        if ($milestone->order->supplier->user_id !== $request->user()->id) {
            return $this->error('Apenas o fornecedor pode enviar evidências.', 403);
        }

        $request->validate([
            'evidence' => 'required|array|min:1|max:6',
            'evidence.*' => 'file|mimes:jpg,jpeg,png,mp4|max:10240',
            'description' => 'nullable|string|max:500',
        ]);

        // Upload files
        $urls = [];
        foreach ($request->file('evidence') as $file) {
            $path = $file->store('milestones/evidence', 'public');
            $urls[] = '/storage/' . $path;
        }

        try {
            $this->milestoneService->submitEvidence($milestone, $urls, $request->description);
            return $this->success($milestone->fresh(), 'Evidência enviada. Aguardando aprovação do cliente.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Customer approves evidence → money released.
     */
    public function approve(Request $request, string $milestoneId): JsonResponse
    {
        $milestone = PaymentMilestone::findOrFail($milestoneId);
        $milestone->loadMissing('order');

        if ($milestone->order->customer_id !== $request->user()->id) {
            return $this->error('Apenas o cliente pode aprovar evidências.', 403);
        }

        try {
            $this->milestoneService->approveEvidence($milestone);

            $message = $milestone->is_final
                ? 'Aprovado. Pagamento será liberado em 48h se não houver contestação.'
                : 'Aprovado. Pagamento liberado ao fornecedor.';

            return $this->success($milestone->fresh(), $message);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Customer declines evidence → supplier must redo.
     */
    public function decline(Request $request, string $milestoneId): JsonResponse
    {
        $milestone = PaymentMilestone::findOrFail($milestoneId);
        $milestone->loadMissing('order');

        if ($milestone->order->customer_id !== $request->user()->id) {
            return $this->error('Apenas o cliente pode recusar evidências.', 403);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $this->milestoneService->declineEvidence($milestone, $request->reason);
            return $this->success($milestone->fresh(), 'Evidência recusada. Fornecedor será notificado.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Customer contests the final milestone within 48h.
     */
    public function contest(Request $request, string $milestoneId): JsonResponse
    {
        $milestone = PaymentMilestone::findOrFail($milestoneId);
        $milestone->loadMissing('order');

        if ($milestone->order->customer_id !== $request->user()->id) {
            return $this->error('Apenas o cliente pode contestar.', 403);
        }

        $request->validate([
            'reason' => 'required|string|min:20|max:1000',
        ]);

        try {
            $this->milestoneService->contestMilestone($milestone, $request->reason);
            return $this->success($milestone->fresh(), 'Contestação registrada. A equipe irá analisar.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Admin resolves a contested milestone.
     */
    public function adminResolve(Request $request, string $milestoneId): JsonResponse
    {
        $milestone = PaymentMilestone::findOrFail($milestoneId);

        $request->validate([
            'resolution' => 'required|in:release,refund',
        ]);

        try {
            $this->milestoneService->adminResolve($milestone, $request->resolution);
            return $this->success($milestone->fresh(), 'Contestação resolvida.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Check if user has access to this order's milestones.
     */
    protected function authorizeAccess(Order $order): void
    {
        $order->loadMissing('supplier');
        $userId = request()->user()->id;
        $isCustomer = $order->customer_id === $userId;
        $isSupplier = $order->supplier && $order->supplier->user_id === $userId;
        $isAdmin = request()->user()->role === 'admin';

        if (!$isCustomer && !$isSupplier && !$isAdmin) {
            abort(403, 'Acesso não autorizado.');
        }
    }
}
