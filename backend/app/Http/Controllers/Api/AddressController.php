<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->get();

        return $this->success($addresses);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => 'nullable|string|max:100',
            'street' => 'required|string|max:255',
            'number' => 'required|string|max:20',
            'complement' => 'nullable|string|max:255',
            'neighborhood' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|size:2',
            'zip_code' => 'required|string|max:10',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_default' => 'nullable|boolean',
        ]);

        $user = $request->user();

        // If setting as default, unset others
        if (!empty($validated['is_default'])) {
            $user->addresses()->update(['is_default' => false]);
        }

        $address = $user->addresses()->create($validated);

        return $this->success($address, 'Endereço cadastrado com sucesso.', 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);

        return $this->success($address);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);

        $validated = $request->validate([
            'label' => 'nullable|string|max:100',
            'street' => 'sometimes|string|max:255',
            'number' => 'sometimes|string|max:20',
            'complement' => 'nullable|string|max:255',
            'neighborhood' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'state' => 'sometimes|string|size:2',
            'zip_code' => 'sometimes|string|max:10',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_default' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            $request->user()->addresses()->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return $this->success($address, 'Endereço atualizado com sucesso.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);
        $address->delete();

        return $this->success(null, 'Endereço removido com sucesso.');
    }
}
