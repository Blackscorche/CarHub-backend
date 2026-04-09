<?php

namespace App\Events;

use App\Models\Delivery;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Delivery $delivery) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('delivery.' . $this->delivery->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'delivery_id' => $this->delivery->id,
            'status' => $this->delivery->status,
            'current_latitude' => $this->delivery->current_latitude,
            'current_longitude' => $this->delivery->current_longitude,
            'estimated_arrival' => $this->delivery->estimated_arrival?->toIso8601String(),
            'delivered_at' => $this->delivery->delivered_at?->toIso8601String(),
        ];
    }
}
