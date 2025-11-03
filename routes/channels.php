<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Canal privado para cada orden
Broadcast::channel('order.{orderId}', function ($user, $orderId) {
    $order = \App\Models\Order::find($orderId);
    
    if (!$order) {
        return false;
    }
    
    // Cliente puede ver sus propios pedidos
    if ($user->id === $order->user_id) {
        return true;
    }
    
    // Admin puede ver todos los pedidos
    if ($user->hasAnyRole(['admin', 'super_admin'])) {
        return true;
    }
    
    // Delivery puede ver pedidos asignados a él (solo si está asignado)
    if ($user->hasRole('delivery') && $order->delivery_id === $user->id && $order->assigned_at) {
        return true;
    }
    
    return false;
});

// Canal privado para notificaciones de usuario
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Canal privado para notificaciones generales de admin
Broadcast::channel('admin.orders', function ($user) {
    return $user->hasAnyRole(['admin', 'super_admin']);
});

