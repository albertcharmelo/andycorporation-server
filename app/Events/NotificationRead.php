<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;

    /**
     * Create a new event instance.
     */
    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $userId = $this->notification->user_id;
        return [
            new PrivateChannel("user.{$userId}"),
        ];
    }

    /**
     * Nombre del evento para broadcasting
     */
    public function broadcastAs(): string
    {
        return 'NotificationRead';
    }

    /**
     * Datos a enviar en el evento
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'is_read' => $this->notification->is_read,
            'read_at' => $this->notification->read_at?->toIso8601String(),
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
