<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $vehicles = $request->user()->vehicles()->orderByDesc('is_default')->get();

        return $this->success($vehicles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'plate' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:50',
            'is_default' => 'nullable|boolean',
        ]);

        $user = $request->user();

        if (!empty($validated['is_default'])) {
            $user->vehicles()->update(['is_default' => false]);
        }

        $vehicle = $user->vehicles()->create($validated);

        return $this->success($vehicle, 'Veículo cadastrado com sucesso.', 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $vehicle = $request->user()->vehicles()->findOrFail($id);

        return $this->success($vehicle);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $vehicle = $request->user()->vehicles()->findOrFail($id);

        $validated = $request->validate([
            'brand' => 'sometimes|string|max:100',
            'model' => 'sometimes|string|max:100',
            'year' => 'sometimes|integer|min:1900|max:' . (date('Y') + 1),
            'plate' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:50',
            'is_default' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            $request->user()->vehicles()->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $vehicle->update($validated);

        return $this->success($vehicle, 'Veículo atualizado com sucesso.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $vehicle = $request->user()->vehicles()->findOrFail($id);
        $vehicle->delete();

        return $this->success(null, 'Veículo removido com sucesso.');
    }
}
