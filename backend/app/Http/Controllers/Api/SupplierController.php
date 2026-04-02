<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\PagarmeClient;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SupplierController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Supplier::approved()->with(['user:id,name', 'address', 'insuranceTags']);

        // Location-based search
        if ($request->filled(['latitude', 'longitude'])) {
            $radius = $request->input('radius_km', 50);
            $query->nearby(
                (float) $request->latitude,
                (float) $request->longitude,
                (float) $radius
            );
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Search by name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        // Min rating filter
        if ($request->filled('min_rating')) {
            $query->where('avg_rating', '>=', (float) $request->min_rating);
        }

        // Insurance filter
        if ($request->filled('insurance_name')) {
            $query->whereHas('insuranceTags', function ($q) use ($request) {
                $q->where('insurance_name', $request->insurance_name);
            });
        }

        // Price range filter (via catalog items)
        if ($request->filled('price_min') || $request->filled('price_max')) {
            $query->whereHas('catalogItems', function ($q) use ($request) {
                $q->where('is_active', true);
                if ($request->filled('price_min')) {
                    $q->where('price', '>=', (float) $request->price_min);
                }
                if ($request->filled('price_max')) {
                    $q->where('price', '<=', (float) $request->price_max);
                }
            });
        }

        // Sorting
        $hasLocation = $request->filled(['latitude', 'longitude']);
        $sortBy = $request->input('sort_by', $hasLocation ? 'distance' : 'rating');
        match ($sortBy) {
            'rating' => $query->orderByDesc('avg_rating'),
            'price' => $query->orderByRaw(
                '(SELECT MIN(price) FROM catalog_items WHERE catalog_items.supplier_id = suppliers.id AND is_active = 1 AND price IS NOT NULL) ASC'
            ),
            'distance' => $hasLocation ? $query->orderBy('distance') : $query->orderByDesc('avg_rating'),
            default => $query->orderByDesc('avg_rating'),
        };

        $perPage = min((int) $request->input('limit', 20), 50);
        $suppliers = $query->paginate($perPage);

        return $this->success($suppliers);
    }

    public function show(string $id): JsonResponse
    {
        $supplier = Supplier::approved()
            ->with([
                'user:id,name,phone,avatar_url',
                'address',
                'catalogItems' => fn ($q) => $q->where('is_active', true),
                'reviews' => fn ($q) => $q->latest()->limit(10)->with('customer:id,name,avatar_url'),
                'insuranceTags',
            ])
            ->findOrFail($id);

        // Add review summary
        $supplier->review_summary = [
            'avg_rating' => $supplier->avg_rating,
            'total_ratings' => $supplier->total_ratings,
            'distribution' => $supplier->reviews()
                ->selectRaw('rating, COUNT(*) as count')
                ->groupBy('rating')
                ->pluck('count', 'rating'),
        ];

        return $this->success($supplier);
    }

    public function nearby(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:1|max:100',
        ]);

        $lat = (float) $request->latitude;
        $lng = (float) $request->longitude;
        $radius = (float) $request->input('radius_km', 50);

        $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(latitude))
            * cos(radians(longitude) - radians(?)) + sin(radians(?))
            * sin(radians(latitude))))";

        $query = Supplier::approved()
            ->select(['id', 'business_name', 'category', 'latitude', 'longitude', 'avg_rating', 'logo_url'])
            ->selectRaw("{$haversine} AS distance", [$lat, $lng, $lat])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->havingRaw("distance < ?", [$radius]);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('min_rating')) {
            $query->where('avg_rating', '>=', (float) $request->min_rating);
        }
        if ($request->filled('insurance_name')) {
            $query->whereHas('insuranceTags', fn ($q) => $q->where('insurance_name', $request->insurance_name));
        }
        if ($request->filled('price_min') || $request->filled('price_max')) {
            $query->whereHas('catalogItems', function ($q) use ($request) {
                $q->where('is_active', true);
                if ($request->filled('price_min')) $q->where('price', '>=', (float) $request->price_min);
                if ($request->filled('price_max')) $q->where('price', '<=', (float) $request->price_max);
            });
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $suppliers = $query->orderBy('distance')->limit(200)->get();

        return $this->success($suppliers);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $supplier = $request->user()->supplier;

        if (!$supplier) {
            return $this->error('Perfil de fornecedor não encontrado.', 404);
        }

        $validated = $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'category' => 'sometimes|in:mecanica,eletrica,funilaria,pneus,estetica,pecas,outros',
            'categories' => 'sometimes|array',
            'service_radius_km' => 'sometimes|integer|min:1|max:100',
            'opening_hours' => 'sometimes|array',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'insurance_partners' => 'sometimes|array',
            // Address fields
            'address' => 'sometimes|array',
            'address.street' => 'required_with:address|string',
            'address.number' => 'required_with:address|string',
            'address.complement' => 'nullable|string',
            'address.neighborhood' => 'required_with:address|string',
            'address.city' => 'required_with:address|string',
            'address.state' => 'required_with:address|string|max:2',
            'address.zip_code' => 'required_with:address|string',
            'address.latitude' => 'nullable|numeric|between:-90,90',
            'address.longitude' => 'nullable|numeric|between:-180,180',
        ]);

        // Handle address update
        if ($request->filled('address')) {
            $addressData = $validated['address'];
            $user = $request->user();

            if ($supplier->address_id) {
                $supplier->address->update($addressData);
            } else {
                $address = $user->addresses()->create($addressData);
                $validated['address_id'] = $address->id;
            }

            // Sync lat/lng from address to supplier for nearby search
            if (! empty($addressData['latitude']) && ! empty($addressData['longitude'])) {
                $validated['latitude'] = $addressData['latitude'];
                $validated['longitude'] = $addressData['longitude'];
            }

            unset($validated['address']);
        }

        $supplier->update($validated);

        // Sync insurance tags if provided
        if ($request->filled('insurance_tags')) {
            $supplier->insuranceTags()->delete();
            foreach ($request->insurance_tags as $tag) {
                $supplier->insuranceTags()->create(['insurance_name' => $tag]);
            }
        }

        return $this->success($supplier->fresh(['insuranceTags', 'address']), 'Perfil atualizado com sucesso.');
    }

    public function uploadCoverImage(Request $request): JsonResponse
    {
        $request->validate([
            'cover_image' => 'required|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $supplier = $request->user()->supplier;

        if (!$supplier) {
            return $this->error('Perfil de fornecedor não encontrado.', 404);
        }

        // Delete old cover image
        if ($supplier->cover_image_url) {
            $oldPath = str_replace('/storage/', '', $supplier->cover_image_url);
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('cover_image')->store('suppliers/covers', 'public');
        $supplier->update(['cover_image_url' => '/storage/' . $path]);

        return $this->success(['cover_image_url' => $supplier->cover_image_url], 'Imagem de capa atualizada.');
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $supplier = $request->user()->supplier;

        if (!$supplier) {
            return $this->error('Perfil de fornecedor não encontrado.', 404);
        }

        if ($supplier->logo_url) {
            $oldPath = str_replace('/storage/', '', $supplier->logo_url);
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('logo')->store('suppliers/logos', 'public');
        $supplier->update(['logo_url' => '/storage/' . $path]);

        return $this->success(['logo_url' => $supplier->logo_url], 'Logo atualizado.');
    }

    /**
     * Register or update supplier bank account + create Pagar.me recipient.
     */
    public function registerBankAccount(Request $request): JsonResponse
    {
        $supplier = $request->user()->supplier;
        if (!$supplier) {
            return $this->error('Perfil de fornecedor não encontrado.', 404);
        }

        $request->validate([
            'bank_code' => 'required|string|size:3',
            'agencia' => 'required|string|max:10',
            'agencia_dv' => 'nullable|string|max:2',
            'conta' => 'required|string|max:20',
            'conta_dv' => 'required|string|max:2',
            'type' => 'required|in:conta_corrente,conta_poupanca',
            'document_type' => 'required|in:cpf,cnpj',
            'document_number' => 'required|string|max:18',
            'legal_name' => 'required|string|max:255',
        ]);

        $pagarme = app(PagarmeClient::class);
        $supplier->loadMissing('user');

        try {
            return DB::transaction(function () use ($request, $supplier, $pagarme) {
                // 1. Create bank account on Pagar.me
                $bankResponse = $pagarme->post('/bank_accounts', [
                    'bank_code' => $request->bank_code,
                    'agencia' => $request->agencia,
                    'agencia_dv' => $request->agencia_dv,
                    'conta' => $request->conta,
                    'conta_dv' => $request->conta_dv,
                    'type' => $request->type,
                    'document_type' => $request->document_type,
                    'document_number' => preg_replace('/\D/', '', $request->document_number),
                    'legal_name' => $request->legal_name,
                ]);

                $bankAccountId = $bankResponse['id'];

                // 2. Save locally
                $bankAccount = $supplier->bankAccount()->updateOrCreate(
                    ['supplier_id' => $supplier->id],
                    [
                        'bank_code' => $request->bank_code,
                        'agencia' => $request->agencia,
                        'agencia_dv' => $request->agencia_dv,
                        'conta' => $request->conta,
                        'conta_dv' => $request->conta_dv,
                        'type' => $request->type,
                        'document_type' => $request->document_type,
                        'document_number' => $request->document_number,
                        'legal_name' => $request->legal_name,
                        'pagarme_bank_account_id' => $bankAccountId,
                    ]
                );

                // 3. Create recipient or update existing recipient's bank account
                if ($supplier->pagarme_recipient_id) {
                    // Update existing recipient's bank account
                    $pagarme->patch("/recipients/{$supplier->pagarme_recipient_id}", [
                        'default_bank_account_id' => $bankAccountId,
                    ]);
                } else {
                    $isCompany = $request->document_type === 'cnpj';

                    $registerInfo = $isCompany ? [
                        'type' => 'corporation',
                        'document' => preg_replace('/\D/', '', $request->document_number),
                        'company_name' => $supplier->business_name,
                        'trading_name' => $supplier->business_name,
                        'email' => $supplier->user->email,
                    ] : [
                        'type' => 'individual',
                        'document' => preg_replace('/\D/', '', $request->document_number),
                        'name' => $request->legal_name,
                        'email' => $supplier->user->email,
                    ];

                    $recipientResponse = $pagarme->post('/recipients', [
                        'name' => $supplier->business_name,
                        'email' => $supplier->user->email,
                        'document' => preg_replace('/\D/', '', $request->document_number),
                        'type' => $isCompany ? 'company' : 'individual',
                        'default_bank_account_id' => $bankAccountId,
                        'transfer_settings' => [
                            'transfer_enabled' => false, // default: manual control
                            'transfer_interval' => 'daily',
                            'transfer_day' => 0,
                        ],
                        'register_information' => $registerInfo,
                    ]);

                    $supplier->update([
                        'pagarme_recipient_id' => $recipientResponse['id'],
                    ]);
                }

                return $this->success([
                    'bank_account' => [
                        'id' => $bankAccount->id,
                        'bank_code' => $bankAccount->bank_code,
                        'agencia' => $bankAccount->agencia,
                        'type' => $bankAccount->type,
                        'legal_name' => $bankAccount->legal_name,
                    ],
                    'recipient_id' => $supplier->fresh()->pagarme_recipient_id,
                ], 'Dados bancários registrados com sucesso.');
            });
        } catch (\Throwable $e) {
            Log::error('Supplier bank account registration failed', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Erro ao registrar dados bancários. Verifique os dados e tente novamente.', 422);
        }
    }

    /**
     * Get supplier bank account info.
     */
    public function getBankAccount(Request $request): JsonResponse
    {
        $supplier = $request->user()->supplier;
        if (!$supplier) {
            return $this->error('Perfil de fornecedor não encontrado.', 404);
        }

        $bankAccount = $supplier->bankAccount;
        if (!$bankAccount) {
            return $this->error('Nenhuma conta bancária cadastrada.', 404);
        }

        return $this->success([
            'id' => $bankAccount->id,
            'bank_code' => $bankAccount->bank_code,
            'agencia' => $bankAccount->agencia,
            'agencia_dv' => $bankAccount->agencia_dv,
            'type' => $bankAccount->type,
            'document_type' => $bankAccount->document_type,
            'legal_name' => $bankAccount->legal_name,
            'has_recipient' => !empty($supplier->pagarme_recipient_id),
        ]);
    }
}
