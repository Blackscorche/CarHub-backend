<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Supplier;

class CouponService
{
    public function validate(string $code, float $subtotal, ?string $supplierId = null, ?string $userId = null): array
    {
        $coupon = Coupon::where('code', strtoupper($code))->first();

        if (! $coupon) {
            return ['valid' => false, 'message' => 'Cupom não encontrado.'];
        }

        if (! $coupon->is_active) {
            return ['valid' => false, 'message' => 'Cupom inativo.'];
        }

        if ($coupon->valid_from && now()->lt($coupon->valid_from)) {
            return ['valid' => false, 'message' => 'Cupom ainda não está válido.'];
        }

        if ($coupon->valid_until && now()->gt($coupon->valid_until)) {
            return ['valid' => false, 'message' => 'Cupom expirado.'];
        }

        if ($coupon->max_uses && $coupon->used_count >= $coupon->max_uses) {
            return ['valid' => false, 'message' => 'Cupom esgotado.'];
        }

        // Check per-user usage limit
        if ($coupon->max_per_user && $userId) {
            $userUsage = $coupon->userUsageCount($userId);
            if ($userUsage >= $coupon->max_per_user) {
                return ['valid' => false, 'message' => 'Você já utilizou este cupom o número máximo de vezes.'];
            }
        }

        if ($coupon->min_order_value && $subtotal < (float) $coupon->min_order_value) {
            return [
                'valid' => false,
                'message' => "Valor mínimo do pedido: R$ " . number_format($coupon->min_order_value, 2, ',', '.'),
            ];
        }

        // Check specific supplier scope
        if ($coupon->supplier_id && $supplierId && $coupon->supplier_id !== $supplierId) {
            return ['valid' => false, 'message' => 'Cupom não válido para este fornecedor.'];
        }

        // Check category match
        if ($coupon->category && $supplierId) {
            $supplier = Supplier::find($supplierId);
            if ($supplier && $supplier->category !== $coupon->category) {
                return ['valid' => false, 'message' => 'Cupom não válido para esta categoria.'];
            }
        }

        // Calculate discount
        $discount = $coupon->discount_type === 'percent'
            ? round($subtotal * ((float) $coupon->discount_value / 100), 2)
            : min((float) $coupon->discount_value, $subtotal);

        return [
            'valid' => true,
            'discount' => $discount,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
            'coupon' => $coupon,
        ];
    }

    public function markUsed(string $code, string $userId, string $orderId, float $discountApplied): void
    {
        $coupon = Coupon::where('code', strtoupper($code))->first();
        if (! $coupon) return;

        // Increment total usage count
        $coupon->increment('used_count');

        // Record per-user usage
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'user_id' => $userId,
            'order_id' => $orderId,
            'discount_applied' => $discountApplied,
        ]);
    }
}
