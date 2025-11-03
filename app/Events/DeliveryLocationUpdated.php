<?php

namespace App\Events;

use App\Models\DeliveryLocation;
use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $location;
    public $order;

    /**
     * Create a new event instance.
     */
    public function __construct(DeliveryLocation $location, Order $order)
    {
        $this->location = $location;
        $this->order = [
            'id' => $order->id,
            'status' => $order->status,
            'current_latitude' => $order->current_latitude,
            'current_longitude' => $order->current_longitude,
            'location_updated_at' => $order->location_updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('order.' . $this->order['id']),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'delivery.location.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'order' => $this->order,
            'location' => [
                'id' => $this->location->id,
                'order_id' => $this->location->order_id,
                'delivery_user_id' => $this->location->delivery_user_id,
                'latitude' => (float) $this->location->latitude,
                'longitude' => (float) $this->location->longitude,
                'created_at' => $this->location->created_at->toIso8601String(),
                'timestamp' => $this->location->created_at->toIso8601String(),
            ],
        ];
    }
}

