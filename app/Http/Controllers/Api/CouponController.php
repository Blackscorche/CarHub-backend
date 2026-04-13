<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CashbackService;
use App\Services\CouponService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CouponService $couponService,
        protected CashbackService $cashbackService,
    ) {}

    /**
     * Validate a coupon code.
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:50',
            'subtotal' => 'required|numeric|min:0',
            'supplier_id' => 'nullable|uuid|exists:suppliers,id',
        ]);

        $result = $this->couponService->validate(
            $request->input('code'),
            $request->input('subtotal'),
            $request->input('supplier_id'),
            $request->user()->id
        );

        if (! $result['valid']) {
            return $this->error($result['message'], 422);
        }

        return $this->success([
            'valid' => true,
            'discount' => $result['discount'],
            'discount_type' => $result['discount_type'],
            'discount_value' => $result['discount_value'],
        ], 'Cupom válido.');
    }

    /**
     * Get cashback wallet info.
     */
    public function cashbackWallet(Request $request): JsonResponse
    {
        $data = $this->cashbackService->getWalletWithTransactions($request->user()->id);

        return $this->success($data);
    }

    /**
     * Get cashback balance only.
     */
    public function cashbackBalance(Request $request): JsonResponse
    {
        $balance = $this->cashbackService->getAvailableBalance($request->user()->id);

        return $this->success(['balance' => $balance]);
    }

    /**
     * Get cashback transactions.
     */
    public function cashbackTransactions(Request $request): JsonResponse
    {
        $data = $this->cashbackService->getWalletWithTransactions($request->user()->id);

        return $this->success($data['transactions'] ?? []);
    }
}
