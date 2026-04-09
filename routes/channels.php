<?php

use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('order.{orderId}', function ($user, $orderId) {
    $order = Order::find($orderId);
    if (! $order) {
        return false;
    }

    return $user->id === $order->customer_id
        || ($user->supplier && $user->supplier->id === $order->supplier_id);
});

Broadcast::channel('supplier.{userId}', function ($user, $userId) {
    return $user->id === $userId;
});

Broadcast::channel('delivery.{deliveryId}', function ($user, $deliveryId) {
    $delivery = Delivery::with('order')->find($deliveryId);
    if (! $delivery) {
        return false;
    }

    $order = $delivery->order;

    return $user->id === $order->customer_id
        || ($user->supplier && $user->supplier->id === $order->supplier_id);
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return $user->id === $userId;
});
