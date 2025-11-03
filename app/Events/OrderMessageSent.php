<?php

namespace App\Events;

use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $sender;
    public $order;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message, User $sender, Order $order)
    {
        $this->message = $message->load('user:id,name,email,avatar');
        $this->sender = [
            'id' => $sender->id,
            'name' => $sender->name,
            'role' => $this->getUserRole($sender, $order),
        ];
        $this->order = [
            'id' => $order->id,
            'status' => $order->status,
            'user_id' => $order->user_id,
            'delivery_id' => $order->delivery_id,
        ];
        
        // Log para debug
        \Log::info('OrderMessageSent creado', [
            'order_id' => $order->id,
            'message_id' => $message->id,
            'channel' => 'private-order.' . $order->id,
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('private-order.' . $this->order['id']),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.message.sent';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'order' => $this->order,
            'message' => [
                'id' => $this->message->id,
                'order_id' => $this->message->order_id,
                'user_id' => $this->message->user_id,
                'message' => $this->message->message,
                'message_type' => $this->message->message_type,
                'file_path' => $this->message->file_path,
                'is_delivery_message' => $this->message->is_delivery_message,
                'is_read' => $this->message->is_read,
                'created_at' => $this->message->created_at->toIso8601String(),
                'user' => [
                    'id' => $this->message->user->id,
                    'name' => $this->message->user->name,
                    'email' => $this->message->user->email,
                    'avatar' => $this->message->user->avatar,
                ],
            ],
            'sender' => $this->sender,
        ];
    }

    /**
     * Determinar el rol del usuario.
     */
    private function getUserRole(User $user, Order $order): string
    {
        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return 'admin';
        }
        
        if ($user->hasRole('delivery') && $order->delivery_id === $user->id) {
            return 'delivery';
        }
        
        if ($order->user_id === $user->id) {
            return 'client';
        }
        
        return 'guest';
    }
}
