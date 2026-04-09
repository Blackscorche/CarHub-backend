<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InsuranceClaim;
use App\Services\InsuranceClaimService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InsuranceClaimController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected InsuranceClaimService $claimService,
    ) {}

    /**
     * List insurance claims for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = InsuranceClaim::with(['order:id,order_number,total', 'supplier.user:id,name']);

        if ($user->role === 'customer') {
            $query->where('customer_id', $user->id);
        } elseif ($user->role === 'supplier') {
            $supplier = $user->supplier;
            if (! $supplier) {
                return $this->success([]);
            }
            $query->where('supplier_id', $supplier->id);
        }
        // admin sees all

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $claims = $query->orderByDesc('created_at')->paginate($request->input('limit', 20));

        return $this->success($claims);
    }

    /**
     * Show a single claim with full protocol data.
     */
    public function show(Request $request, InsuranceClaim $claim): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'customer' && $claim->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }

        if ($user->role === 'supplier') {
            $supplier = $user->supplier;
            if (! $supplier || $claim->supplier_id !== $supplier->id) {
                return $this->forbidden('Acesso negado.');
            }
        }

        $claim->load(['order.items', 'order.vehicle', 'customer', 'supplier.user']);

        return $this->success($claim);
    }

    /**
     * Create a new insurance claim (customer).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|uuid|exists:orders,id',
            'supplier_id' => 'required|uuid|exists:suppliers,id',
            'insurance_name' => 'required|string|max:100',
            'policy_number' => 'nullable|string|max:50',
            'claim_type' => 'required|in:collision,theft,natural_disaster,mechanical,glass,third_party,other',
            'description' => 'required|string|min:20|max:3000',
            'evidence' => 'nullable|array|max:10',
            'evidence.*' => 'file|mimes:jpg,jpeg,png,pdf|max:5120',
            'vehicle_info' => 'nullable|array',
        ]);

        $user = $request->user();

        // Upload evidence files
        $evidenceUrls = [];
        if ($request->hasFile('evidence')) {
            foreach ($request->file('evidence') as $file) {
                $path = $file->store('insurance-claims/' . $user->id, 'public');
                $evidenceUrls[] = Storage::url($path);
            }
        }

        try {
            $claim = $this->claimService->create(
                array_merge($request->all(), ['evidence_urls' => $evidenceUrls]),
                $user->id
            );

            return $this->created($claim, 'Sinistro criado com sucesso. Protocolo: ' . $claim->protocol_number);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Submit a draft claim for processing.
     */
    public function submit(Request $request, InsuranceClaim $claim): JsonResponse
    {
        $user = $request->user();
        if ($claim->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }

        try {
            $claim = $this->claimService->submit($claim);
            return $this->success($claim, 'Sinistro enviado para processamento.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Send claim to insurer (admin or system action).
     */
    public function sendToInsurer(Request $request, InsuranceClaim $claim): JsonResponse
    {
        try {
            $claim = $this->claimService->sendToInsurer($claim);
            return $this->success($claim, 'Sinistro encaminhado à seguradora.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Update claim status (admin action).
     */
    public function updateStatus(Request $request, InsuranceClaim $claim): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:under_analysis,approved,partially_approved,rejected,completed',
            'insurer_response' => 'nullable|string|max:2000',
            'insurer_reference' => 'nullable|string|max:100',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        try {
            if ($request->has('insurer_reference')) {
                $claim->update(['insurer_reference' => $request->input('insurer_reference')]);
            }

            $claim = $this->claimService->updateStatus(
                $claim,
                $request->input('status'),
                $request->input('insurer_response'),
                $request->input('admin_notes')
            );

            return $this->success($claim, 'Status do sinistro atualizado.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Download protocol data as JSON.
     */
    public function downloadProtocol(Request $request, InsuranceClaim $claim): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'customer' && $claim->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }

        $protocolData = $claim->protocol_data ?? $this->claimService->buildProtocolData($claim);

        return response()->json($protocolData, 200, [
            'Content-Disposition' => 'attachment; filename="protocolo-' . $claim->protocol_number . '.json"',
        ]);
    }
}
