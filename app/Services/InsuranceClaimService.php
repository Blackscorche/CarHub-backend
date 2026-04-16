<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InsuranceClaimService
{
    /**
     * Generate a unique structured protocol number.
     * Format: CLM-YYYYMMDD-XXXXX
     */
    public function generateProtocolNumber(): string
    {
        $prefix = 'CLM-' . date('Ymd') . '-';
        $last = InsuranceClaim::where('protocol_number', 'like', $prefix . '%')
            ->orderByDesc('protocol_number')
            ->first();

        $seq = 1;
        if ($last) {
            $seq = ((int) Str::afterLast($last->protocol_number, '-')) + 1;
        }

        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Build the structured protocol data (JSON) for the insurance claim.
     */
    public function buildProtocolData(InsuranceClaim $claim): array
    {
        $order = $claim->order()->with(['items', 'vehicle'])->first();
        $customer = $claim->customer;
        $supplier = $claim->supplier()->with('user')->first();

        $vehicle = $order?->vehicle;

        return [
            'protocol_number' => $claim->protocol_number,
            'created_at' => $claim->created_at->toIso8601String(),
            'insurance' => [
                'name' => $claim->insurance_name,
                'policy_number' => $claim->policy_number,
            ],
            'claim' => [
                'type' => $claim->claim_type,
                'description' => $claim->description,
                'evidence_count' => count($claim->evidence_urls ?? []),
            ],
            'customer' => [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'cpf' => $customer->cpf,
            ],
            'vehicle' => $vehicle ? [
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'plate' => $vehicle->plate,
                'color' => $vehicle->color,
            ] : ($claim->vehicle_info ?? null),
            'supplier' => [
                'business_name' => $supplier->business_name,
                'cnpj' => $supplier->cnpj,
                'category' => $supplier->category,
                'contact_email' => $supplier->user->email,
                'contact_phone' => $supplier->user->phone,
            ],
            'order' => $order ? [
                'order_number' => $order->order_number,
                'total' => (float) $order->total,
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total_price' => (float) $item->total_price,
                ])->toArray(),
            ] : null,
        ];
    }

    /**
     * Create a new insurance claim.
     */
    public function create(array $data, string $customerId): InsuranceClaim
    {
        return DB::transaction(function () use ($data, $customerId) {
            $claim = InsuranceClaim::create([
                'order_id' => $data['order_id'],
                'customer_id' => $customerId,
                'supplier_id' => $data['supplier_id'],
                'protocol_number' => $this->generateProtocolNumber(),
                'insurance_name' => $data['insurance_name'],
                'policy_number' => $data['policy_number'] ?? null,
                'claim_type' => $data['claim_type'],
                'description' => $data['description'],
                'evidence_urls' => $data['evidence_urls'] ?? [],
                'vehicle_info' => $data['vehicle_info'] ?? null,
                'status' => 'draft',
            ]);

            // Build and attach protocol data
            $protocolData = $this->buildProtocolData($claim);
            $claim->update(['protocol_data' => $protocolData]);

            return $claim;
        });
    }

    /**
     * Submit a draft claim — marks as submitted and ready for sending.
     */
    public function submit(InsuranceClaim $claim): InsuranceClaim
    {
        if ($claim->status !== 'draft') {
            throw new \InvalidArgumentException('Apenas sinistros em rascunho podem ser enviados.');
        }

        // Refresh protocol data before submission
        $protocolData = $this->buildProtocolData($claim);
        $claim->update([
            'status' => 'submitted',
            'protocol_data' => $protocolData,
        ]);

        return $claim->fresh();
    }

    /**
     * Send claim to insurer via email with structured protocol JSON.
     */
    public function sendToInsurer(InsuranceClaim $claim): InsuranceClaim
    {
        if (! in_array($claim->status, ['submitted', 'sent_to_insurer'])) {
            throw new \InvalidArgumentException('Sinistro precisa estar enviado para ser encaminhado à seguradora.');
        }

        $protocolData = $claim->protocol_data ?? $this->buildProtocolData($claim);

        // Determine insurer email from platform config or known insurers
        $insurerEmail = $this->getInsurerEmail($claim->insurance_name);

        if ($insurerEmail) {
            try {
                Mail::raw(
                    "Protocolo de Sinistro CarHub\n\n" .
                    "Número do Protocolo: {$claim->protocol_number}\n" .
                    "Seguradora: {$claim->insurance_name}\n" .
                    "Apólice: {$claim->policy_number}\n" .
                    "Tipo: {$claim->claim_type}\n\n" .
                    "Descrição:\n{$claim->description}\n\n" .
                    "Dados completos em anexo (JSON).\n\n" .
                    "---\n" .
                    json_encode($protocolData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    function ($message) use ($insurerEmail, $claim) {
                        $message->to($insurerEmail)
                            ->subject("Sinistro CarHub - Protocolo {$claim->protocol_number}")
                            ->replyTo(config('mail.from.address', 'suporte@carhubrasil.com.br'));
                    }
                );

                Log::info('Insurance claim sent to insurer', [
                    'claim_id' => $claim->id,
                    'insurer_email' => $insurerEmail,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send claim to insurer', [
                    'claim_id' => $claim->id,
                    'error' => $e->getMessage(),
                ]);
                throw new \RuntimeException('Erro ao enviar sinistro para a seguradora.');
            }
        }

        $claim->update([
            'status' => 'sent_to_insurer',
            'sent_at' => now(),
            'protocol_data' => $protocolData,
        ]);

        return $claim->fresh();
    }

    /**
     * Update claim status (admin action — insurer response).
     */
    public function updateStatus(InsuranceClaim $claim, string $status, ?string $insurerResponse = null, ?string $adminNotes = null): InsuranceClaim
    {
        $allowed = ['under_analysis', 'approved', 'partially_approved', 'rejected', 'completed'];
        if (! in_array($status, $allowed)) {
            throw new \InvalidArgumentException("Status inválido: {$status}");
        }

        $updateData = ['status' => $status];

        if ($insurerResponse) {
            $updateData['insurer_response'] = $insurerResponse;
            $updateData['responded_at'] = now();
        }

        if ($adminNotes) {
            $updateData['admin_notes'] = $adminNotes;
        }

        if ($status === 'completed') {
            $updateData['completed_at'] = now();
        }

        $claim->update($updateData);

        return $claim->fresh();
    }

    /**
     * Resolve insurer email from known partners.
     */
    protected function getInsurerEmail(string $insuranceName): ?string
    {
        // Map of known insurer emails — could be stored in platform_configs
        $knownInsurers = [
            'porto_seguro' => 'sinistros@portoseguro.com.br',
            'bradesco_seguros' => 'sinistros@bradescoseguros.com.br',
            'sulamerica' => 'sinistros@sulamerica.com.br',
            'liberty' => 'sinistros@libertyseguros.com.br',
            'tokio_marine' => 'sinistros@tokiomarine.com.br',
            'allianz' => 'sinistros@allianz.com.br',
            'mapfre' => 'sinistros@mapfre.com.br',
            'hdi' => 'sinistros@hdi.com.br',
            'zurich' => 'sinistros@zurich.com.br',
        ];

        $key = Str::slug($insuranceName, '_');

        return $knownInsurers[$key] ?? null;
    }
}
