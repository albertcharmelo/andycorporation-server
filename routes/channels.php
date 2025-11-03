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

// Canal privado para cada orden (formato: private-order.{orderId})
Broadcast::channel('private-order.{orderId}', function ($user, $orderId) {
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
    
    return true;
});

// Canal privado para notificaciones de usuario (formato: private-user.{userId})
Broadcast::channel('private-user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Canal privado para notificaciones generales de admin (formato: private-admin.orders)
Broadcast::channel('private-admin.orders', function ($user) {
    return $user->hasAnyRole(['admin', 'super_admin']);
});

