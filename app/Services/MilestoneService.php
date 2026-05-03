<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentMilestone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MilestoneService
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Customer pays a portion of the order → creates a milestone + Pagar.me charge.
     */
    public function payMilestone(Order $order, float $percentage, string $paymentMethod, ?string $cardToken = null): array
    {
        $order->loadMissing(['items', 'customer', 'supplier', 'milestones']);

        $totalPaid = $order->milestones->sum('amount');
        $remainingAmount = round((float) $order->total - $totalPaid, 2);

        if ($remainingAmount <= 0) {
            throw new \RuntimeException('Pedido já está totalmente pago.');
        }

        $amount = round((float) $order->total * ($percentage / 100), 2);
        $amount = min($amount, $remainingAmount); // can't pay more than remaining

        $isLastPayment = ($amount >= $remainingAmount - 0.01); // float tolerance
        $actualPercentage = round(($amount / (float) $order->total) * 100, 2);

        $nextSequence = ($order->milestones->max('sequence') ?? 0) + 1;

        // Create payment via PaymentService
        // Use a clone of the order and override total temporarily for amount calculation.
        // PaymentService::buildItems() will handle the partial amount via its fallback.
        $fakeOrder = clone $order;
        $fakeOrder->total = $amount;

        // Unique idempotency key per milestone sequence to avoid cached responses
        $idempotencyKey = "milestone-{$paymentMethod}-{$order->id}-seq{$nextSequence}";

        if ($paymentMethod === 'pix') {
            $paymentResult = $this->paymentService->createPixPayment($fakeOrder, 'full', $idempotencyKey);
        } else {
            if (!$cardToken) {
                throw new \RuntimeException('Token do cartão é obrigatório.');
            }
            $paymentResult = $this->paymentService->createCardPayment($fakeOrder, $cardToken, 'full', 1, $paymentMethod, $idempotencyKey);
        }

        // Get the actual Pagar.me charge ID from the Payment record
        $payment = \App\Models\Payment::find($paymentResult['payment_id'] ?? null);
        $pagarmeChargeId = $payment?->pagarme_charge_id;

        // Create milestone record
        $milestone = PaymentMilestone::create([
            'order_id' => $order->id,
            'sequence' => $nextSequence,
            'amount' => $amount,
            'percentage' => $actualPercentage,
            'pagarme_charge_id' => $pagarmeChargeId,
            'status' => 'paid',
            'is_final' => $isLastPayment,
            'paid_at' => now(),
        ]);

        // Update order status
        if ($isLastPayment) {
            $order->update(['status' => 'paid']);
        } else {
            $order->update([
                'status' => 'partially_paid',
                'partial_payment_amount' => $totalPaid + $amount,
                'remaining_amount' => $remainingAmount - $amount,
            ]);
        }

        return [
            'milestone_id' => $milestone->id,
            'sequence' => $milestone->sequence,
            'amount' => $milestone->amount,
            'percentage' => $milestone->percentage,
            'is_final' => $milestone->is_final,
            'remaining' => round($remainingAmount - $amount, 2),
            'payment' => $paymentResult,
        ];
    }

    /**
     * Supplier submits evidence for a milestone.
     */
    public function submitEvidence(PaymentMilestone $milestone, array $evidenceUrls, ?string $description = null): void
    {
        if (!$milestone->isPaid() && !$milestone->isDeclined()) {
            throw new \RuntimeException('Este marco não está aguardando entrega.');
        }

        $milestone->update([
            'status' => 'delivered',
            'evidence_urls' => $evidenceUrls,
            'evidence_description' => $description,
            'delivered_at' => now(),
            'declined_at' => null, // clear previous decline
        ]);

        event(new \App\Events\EvidenceUploaded($milestone));
    }

    /**
     * Customer approves evidence → money released to supplier.
     */
    public function approveEvidence(PaymentMilestone $milestone): void
    {
        if (!$milestone->isDelivered()) {
            throw new \RuntimeException('Nenhuma evidência para aprovar.');
        }

        $milestone->loadMissing('order.supplier');

        DB::transaction(function () use ($milestone) {
            $milestone->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            $supplier = $milestone->order->supplier;

            if ($milestone->is_final) {
                // Last payment → 48h contestation window, don't release yet
                dispatch(new \App\Jobs\ReleaseAfterContestationJob($milestone))
                    ->delay(now()->addHours(48));
            } else {
                // Not final → release immediately
                $this->releaseMilestone($milestone, $supplier);
            }
        });

        event(new \App\Events\MilestoneApproved($milestone));
    }

    /**
     * Customer declines evidence → supplier must redo.
     */
    public function declineEvidence(PaymentMilestone $milestone, ?string $reason = null): void
    {
        if (!$milestone->isDelivered()) {
            throw new \RuntimeException('Nenhuma evidência para recusar.');
        }

        $milestone->update([
            'status' => 'declined',
            'declined_at' => now(),
            'contest_reason' => $reason,
        ]);

        event(new \App\Events\MilestoneDeclined($milestone));
    }

    /**
     * Customer contests the final milestone within 48h.
     */
    public function contestMilestone(PaymentMilestone $milestone, string $reason): void
    {
        if (!$milestone->is_final) {
            throw new \RuntimeException('Apenas o pagamento final pode ser contestado.');
        }

        if (!$milestone->isApproved()) {
            throw new \RuntimeException('Este marco ainda não foi aprovado.');
        }

        if (!$milestone->isInContestationWindow()) {
            throw new \RuntimeException('Prazo de 48h para contestação expirado.');
        }

        $milestone->update([
            'status' => 'contested',
            'contested_at' => now(),
            'contest_reason' => $reason,
        ]);

        event(new \App\Events\MilestoneDisputed($milestone));
    }

    /**
     * Release money to supplier (called directly or after 48h).
     */
    public function releaseMilestone(PaymentMilestone $milestone, $supplier = null): void
    {
        if (!$supplier) {
            $milestone->loadMissing('order.supplier');
            $supplier = $milestone->order->supplier;
        }

        if (!$supplier->pagarme_recipient_id) {
            Log::error('Cannot release: supplier has no Pagar.me recipient', [
                'milestone_id' => $milestone->id,
                'supplier_id' => $supplier->id,
            ]);
            return;
        }

        try {
            // Find the Payment record linked to this milestone and release it properly
            // so the Payment status is updated (avoids double-release by ReleaseHeldPayments job)
            $payment = \App\Models\Payment::where('pagarme_charge_id', $milestone->pagarme_charge_id)
                ->whereIn('status', ['held', 'confirmed'])
                ->first();

            if ($payment) {
                $this->paymentService->releasePayment($payment);
            } else {
                // Fallback: direct withdrawal if no matching Payment record found
                $this->paymentService->manualWithdraw(
                    $supplier->pagarme_recipient_id,
                    (float) $milestone->amount
                );
            }

            $milestone->update([
                'released_at' => now(),
            ]);

            event(new \App\Events\MilestoneReleased($milestone));
        } catch (\Throwable $e) {
            Log::error('Milestone release failed', [
                'milestone_id' => $milestone->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Auto-release after 48h contestation window (called by Job).
     */
    public function autoReleaseAfterContestation(PaymentMilestone $milestone): void
    {
        $milestone = $milestone->fresh();

        // If contested or already released, skip
        if ($milestone->isContested() || $milestone->released_at !== null) {
            return;
        }

        $this->releaseMilestone($milestone);
    }

    /**
     * Admin resolves a contested milestone.
     */
    public function adminResolve(PaymentMilestone $milestone, string $resolution): void
    {
        if (!$milestone->isContested()) {
            throw new \RuntimeException('Este marco não está contestado.');
        }

        if ($resolution === 'release') {
            $this->releaseMilestone($milestone);
        } elseif ($resolution === 'refund') {
            $milestone->update([
                'status' => 'refunded',
            ]);
            // Refund via Pagar.me — find the payment linked to this milestone
            $payment = \App\Models\Payment::where('pagarme_charge_id', $milestone->pagarme_charge_id)
                ->whereIn('status', ['held', 'confirmed'])
                ->first();

            if ($payment) {
                $this->paymentService->refund($payment, (float) $milestone->amount);
            }
        }
    }

    /**
     * Get order milestone summary.
     */
    public function getOrderSummary(Order $order): array
    {
        $order->loadMissing('milestones');

        $totalPaid = $order->milestones->sum('amount');
        $totalReleased = $order->milestones->where('released_at', '!=', null)->sum('amount');

        return [
            'order_total' => (float) $order->total,
            'total_paid' => round($totalPaid, 2),
            'total_released' => round($totalReleased, 2),
            'remaining' => round((float) $order->total - $totalPaid, 2),
            'remaining_percentage' => (float) $order->total > 0
                ? round(((float) $order->total - $totalPaid) / (float) $order->total * 100, 2)
                : 0,
            'milestones' => $order->milestones,
            'can_pay_more' => $totalPaid < (float) $order->total,
            'all_approved' => $order->milestones->every(fn ($m) => $m->isApproved()),
        ];
    }
}
