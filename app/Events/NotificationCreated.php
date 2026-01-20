<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;

    /**
     * Create a new event instance.
     */
    public function __construct(Notification $notification)
    {
        $this->notification = $notification->load('user:id,name,email');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $userId = $this->notification->user_id;
        
        // Determinar el canal según el rol del usuario
        $user = $this->notification->user;
        
        if ($user && $user->hasAnyRole(['admin', 'super_admin'])) {
            // Admin recibe en canal privado de admin
            // Laravel automáticamente agrega el prefijo 'private-', así que usamos 'admin.notifications' y 'user.{userId}'
            return [
                new PrivateChannel('admin.notifications'),
                new PrivateChannel("user.{$userId}"), // También en su canal personal
            ];
        }
        
        // Cliente o delivery reciben en su canal personal
        // Laravel automáticamente agrega el prefijo 'private-', así que usamos 'user.{userId}'
        return [
            new PrivateChannel("user.{$userId}"),
        ];
    }

    /**
     * Nombre del evento para broadcasting
     */
    public function broadcastAs(): string
    {
        return 'NotificationCreated';
    }

    /**
     * Datos a enviar en el evento
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'user_id' => $this->notification->user_id,
            'type' => $this->notification->type,
            'title' => $this->notification->title,
            'message' => $this->notification->message,
            'data' => $this->notification->data,
            'is_read' => $this->notification->is_read,
            'read_at' => $this->notification->read_at?->toIso8601String(),
            'created_at' => $this->notification->created_at->toIso8601String(),
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
