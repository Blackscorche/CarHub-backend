<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WithdrawalService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    use ApiResponse;

    protected WithdrawalService $withdrawalService;

    public function __construct(WithdrawalService $withdrawalService)
    {
        $this->withdrawalService = $withdrawalService;
    }

    public function index(Request $request): JsonResponse
    {
        $supplier = $request->user()->supplier;
        $withdrawals = $this->withdrawalService->getWithdrawals(
            $supplier->id,
            $request->input('limit', 20)
        );

        return $this->success($withdrawals);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $supplier = $request->user()->supplier;
        $withdrawal = $supplier->withdrawals()->findOrFail($id);

        return $this->success($withdrawal);
    }

    public function requestWithdrawal(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:10',
        ]);

        $supplier = $request->user()->supplier;

        try {
            $withdrawal = $this->withdrawalService->requestWithdrawal(
                $supplier,
                (float) $request->amount
            );

            return $this->created($withdrawal, 'Saque solicitado com sucesso.');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
