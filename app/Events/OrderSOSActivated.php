<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderSOSActivated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        // Cargar relaciones necesarias
        $this->order = $order->load(['user:id,name', 'delivery:id,name']);
    }

    /**
     * Canal donde se emitirá el evento
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin')];
    }

    /**
     * Nombre del evento para broadcasting
     */
    public function broadcastAs(): string
    {
        return 'OrderSOSActivated';
    }

    /**
     * Datos a enviar en el evento
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => 'ORD-' . str_pad($this->order->id, 6, '0', STR_PAD_LEFT),
            'sos_comment' => $this->order->sos_comment,
            'sos_reported_at' => $this->order->sos_reported_at?->toIso8601String(),
            'delivery_name' => $this->order->delivery?->name,
            'order' => [
                'id' => $this->order->id,
                'order_number' => 'ORD-' . str_pad($this->order->id, 6, '0', STR_PAD_LEFT),
                'sos_status' => $this->order->sos_status,
                'sos_comment' => $this->order->sos_comment,
                'sos_reported_at' => $this->order->sos_reported_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Condición para emitir el evento
     */
    public function broadcastWhen(): bool
    {
        return config('broadcasting.default') !== 'null';
    }
}
