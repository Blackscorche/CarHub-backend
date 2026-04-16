<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DeliveryController extends Controller
{
    use ApiResponse;

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|uuid|exists:orders,id',
            'delivery_method' => 'required|in:supplier,third_party',
            'third_party_name' => 'required_if:delivery_method,third_party|string|max:100',
            'tracking_code' => 'nullable|string|max:100',
        ]);

        $order = Order::findOrFail($request->input('order_id'));
        $this->authorizeSupplier($request, $order);

        if ($order->delivery) {
            return $this->error('Entrega já criada para este pedido.', 422);
        }

        $delivery = Delivery::create([
            'order_id' => $order->id,
            'delivery_method' => $request->input('delivery_method'),
            'third_party_name' => $request->input('third_party_name'),
            'tracking_code' => $request->input('tracking_code'),
            'status' => 'preparing',
        ]);

        return $this->created($delivery, 'Entrega criada.');
    }

    public function updateStatus(Request $request, Delivery $delivery): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:preparing,picked_up,in_transit,delivered',
        ]);

        $order = $delivery->order;
        $this->authorizeSupplier($request, $order);

        $updateData = ['status' => $request->input('status')];

        if ($request->input('status') === 'delivered') {
            $updateData['delivered_at'] = now();

            if ($order->status === 'completed' || $order->status === 'in_progress') {
                $order->update(['status' => 'delivered']);
            }
        }

        $delivery->update($updateData);

        broadcast(new \App\Events\DeliveryLocationUpdated($delivery))->toOthers();

        return $this->success($delivery->fresh(), 'Status da entrega atualizado.');
    }

    public function updateLocation(Request $request, Delivery $delivery): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'estimated_arrival' => 'nullable|date',
        ]);

        $lat = (float) $request->input('latitude');
        $lng = (float) $request->input('longitude');

        // Recalculate ETA from current position to the delivery address.
        // Fall back to the client-supplied value if we can't geocode the destination.
        $eta = $request->input('estimated_arrival');
        $delivery->loadMissing('order.deliveryAddress');
        $destination = $delivery->order?->deliveryAddress;
        if ($destination && $destination->latitude && $destination->longitude) {
            $distanceKm = $this->haversineKm($lat, $lng, (float) $destination->latitude, (float) $destination->longitude);
            $minutes = (int) max(1, round(($distanceKm / 45) * 60)); // avg urban speed 45 km/h
            $eta = now()->addMinutes($minutes)->toIso8601String();
        }

        $delivery->update([
            'current_latitude' => $lat,
            'current_longitude' => $lng,
            'estimated_arrival' => $eta,
        ]);

        broadcast(new \App\Events\DeliveryLocationUpdated($delivery))->toOthers();

        return $this->success($delivery->fresh());
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'customer' && $order->customer_id !== $user->id) {
            return $this->forbidden('Acesso negado.');
        }
        if ($user->role === 'supplier') {
            $supplier = $user->supplier;
            if (! $supplier || $order->supplier_id !== $supplier->id) {
                return $this->forbidden('Acesso negado.');
            }
        }

        $delivery = $order->delivery;
        if (! $delivery) {
            return $this->notFound('Entrega não encontrada.');
        }

        return $this->success($delivery);
    }

    public function uploadPhoto(Request $request, Delivery $delivery): JsonResponse
    {
        $request->validate([
            'photo' => 'required|file|mimes:jpg,jpeg,png|max:5120',
        ]);

        $path = $request->file('photo')->store('deliveries/' . $delivery->id, 'public');
        $delivery->update(['delivery_photo_url' => Storage::url($path)]);

        return $this->success($delivery->fresh(), 'Foto de entrega enviada.');
    }

    protected function authorizeSupplier(Request $request, Order $order): void
    {
        $user = $request->user();
        $supplier = $user->supplier;
        if (! $supplier || $order->supplier_id !== $supplier->id) {
            abort(403, 'Acesso negado.');
        }
    }
}
