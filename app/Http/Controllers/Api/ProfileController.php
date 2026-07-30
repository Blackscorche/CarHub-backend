<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rules\Cpf;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponse;

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['supplier.address', 'addresses', 'vehicles']);

        return $this->success($user);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => ['sometimes', 'regex:/^(\+55)?\d{10,11}$/'],
            'cpf' => ['sometimes', 'nullable', 'string', new Cpf],
            'fcm_token' => 'sometimes|nullable|string',
        ]);

        // Strip CPF formatting before storing (keep only digits)
        if (isset($validated['cpf']) && $validated['cpf']) {
            $validated['cpf'] = preg_replace('/\D/', '', $validated['cpf']);
        }

        $user->update($validated);

        // Update supplier profile if applicable
        if ($user->isSupplier() && $request->has('supplier')) {
            $supplierData = $request->validate([
                'supplier.business_name' => 'sometimes|string|max:255',
                'supplier.description' => 'sometimes|nullable|string',
                'supplier.category' => 'sometimes|in:mecanica,eletrica,funilaria,pneus,estetica,pecas,outros',
                'supplier.categories' => 'sometimes|array',
                'supplier.service_radius_km' => 'sometimes|integer|min:1|max:100',
                'supplier.opening_hours' => 'sometimes|array',
            ]);

            $user->supplier->update($supplierData['supplier']);
        }

        return $this->success($user->fresh(['supplier']), 'Perfil atualizado com sucesso.');
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar_url) {
            $oldPath = str_replace('/storage/', '', $user->avatar_url);
            \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_url' => '/storage/' . $path]);

        return $this->success(['avatar_url' => $user->avatar_url], 'Avatar atualizado com sucesso.');
    }

    public function updatePushToken(Request $request): JsonResponse
    {
        $request->validate([
            'expo_push_token' => 'required|string',
        ]);

        $request->user()->update(['fcm_token' => $request->expo_push_token]);

        return $this->success(null, 'Token de push atualizado.');
    }
}
