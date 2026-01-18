<?php

namespace App\Events;

use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
 
class OrderMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels; 

    public $message;
    public $sender;
    public $order;

    public function __construct(Message $message, ?User $sender, Order $order)
    {
        // Cargar user solo si existe (no para mensajes del sistema)
        if ($message->user_id) {
            $this->message = $message->load(['user:id,name,email,avatar' => function ($query) {
                // Cargar roles del usuario
            }]);
            // Cargar roles después de cargar el user
            if ($this->message->user) {
                $this->message->user->load('roles:name');
            }
        } else {
            $this->message = $message;
        }
        
        $this->sender = $sender ? [
            'id' => $sender->id,
            'name' => $sender->name,
            'role' => $this->getUserRole($sender, $order),
        ] : [
            'id' => null,
            'name' => 'Sistema',
            'role' => 'system',
        ];
        
        $this->order = [
            'id' => $order->id,
            'status' => $order->status,
            'user_id' => $order->user_id,
            'delivery_id' => $order->delivery_id,
        ];
    }

    public function broadcastOn(): array
    {
        // 👉 sin 'private-'
        return [ new PrivateChannel('order.' . $this->order['id']) ];
    }

    public function broadcastAs(): string
    {
        return 'order.message.sent';
    }

    public function broadcastWhen(): bool
    {
        return config('broadcasting.default') !== 'null';
    }

    public function broadcastWith(): array
    {
        $messageData = [
            'id' => $this->message->id,
            'order_id' => $this->message->order_id,
            'user_id' => $this->message->user_id,
            'message' => $this->message->message,
            'message_type' => $this->message->message_type,
            'file_path' => $this->message->file_path,
            'is_delivery_message' => $this->message->is_delivery_message,
            'is_system' => $this->message->is_system ?? false,
            'system_message_type' => $this->message->system_message_type ?? null,
            'is_read' => $this->message->is_read,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];

        // Incluir user solo si existe (no para mensajes del sistema)
        if ($this->message->user_id && $this->message->relationLoaded('user') && $this->message->user) {
            $userData = [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
                'email' => $this->message->user->email,
                'avatar' => $this->message->user->avatar,
            ];
            
            // Incluir roles si están cargados
            if ($this->message->user->relationLoaded('roles')) {
                $userData['roles'] = $this->message->user->roles->map(function ($role) {
                    return ['name' => $role->name];
                })->toArray();
            }
            
            $messageData['user'] = $userData;
        } else {
            $messageData['user'] = null;
        }

        return [
            'order' => $this->order,
            'message' => $messageData,
            'sender' => $this->sender,
        ];
    }

    private function getUserRole(User $user, Order $order): string
    {
        if ($user->hasAnyRole(['admin', 'super_admin'])) return 'admin';
        if ($user->hasRole('delivery') && $order->delivery_id === $user->id) return 'delivery';
        if ($order->user_id === $user->id) return 'client';
        return 'guest';
    }
}

