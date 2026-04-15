<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use App\Models\Supplier;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CatalogItemController extends Controller
{
    use ApiResponse;

    public function index(Request $request, string $supplierId): JsonResponse
    {
        $supplier = Supplier::findOrFail($supplierId);

        $user = Auth::guard('sanctum')->user();
        $isOwner = $user && $user->supplier && $user->supplier->id === $supplier->id;

        $query = $supplier->catalogItems();
        if (!$isOwner) {
            $query->where('is_active', true);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $items = $query->orderBy('name')->get();

        return $this->success($items);
    }

    public function store(Request $request): JsonResponse
    {
        $supplier = $request->user()->supplier;

        if (!$supplier) {
            return $this->error('Perfil de fornecedor não encontrado.', 404);
        }

        $validated = $request->validate([
            'type' => 'required|in:service,product',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'price_type' => 'required|in:fixed,quote_required',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
            'category' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'stock_quantity' => 'nullable|integer|min:0',
            'requires_scheduling' => 'nullable|boolean',
        ]);

        $item = $supplier->catalogItems()->create($validated);

        return $this->success($item, 'Item adicionado ao catálogo.', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $supplier = $request->user()->supplier;

        if (!$supplier) {
            return $this->error('Perfil de fornecedor não encontrado.', 404);
        }

        $item = $supplier->catalogItems()->findOrFail($id);

        $validated = $request->validate([
            'type' => 'sometimes|in:service,product',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'price_type' => 'sometimes|in:fixed,quote_required',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
            'category' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'stock_quantity' => 'nullable|integer|min:0',
            'requires_scheduling' => 'nullable|boolean',
        ]);

        $item->update($validated);

        return $this->success($item, 'Item atualizado com sucesso.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $supplier = $request->user()->supplier;

        if (!$supplier) {
            return $this->error('Perfil de fornecedor não encontrado.', 404);
        }

        $item = $supplier->catalogItems()->findOrFail($id);
        $item->update(['is_active' => false]);

        return $this->success(null, 'Item removido do catálogo.');
    }

    public function uploadImages(Request $request, string $id): JsonResponse
    {
        $supplier = $request->user()->supplier;

        if (!$supplier) {
            return $this->error('Perfil de fornecedor não encontrado.', 404);
        }

        $item = $supplier->catalogItems()->findOrFail($id);

        $request->validate([
            'images' => 'required|array|max:5',
            'images.*' => 'image|mimes:jpg,jpeg,png|max:3072',
        ]);

        $existingUrls = $item->image_urls ?? [];

        if (count($existingUrls) + count($request->file('images')) > 5) {
            return $this->error('Máximo de 5 imagens por item.', 422);
        }

        $optimizer = app(\App\Services\ImageOptimizerService::class);
        $newUrls = [];
        foreach ($request->file('images') as $image) {
            $newUrls[] = $optimizer->storeOptimized($image, 'catalog', 'public', 1600, 1200, 82);
        }

        $item->update(['image_urls' => array_merge($existingUrls, $newUrls)]);

        return $this->success($item, 'Imagens adicionadas com sucesso.');
    }
}
