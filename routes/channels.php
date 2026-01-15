<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
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
// NOTA: Laravel automáticamente agrega el prefijo 'private-' cuando se usa Broadcast::channel()
// Por lo tanto, registramos como 'order.{orderId}' y Laravel lo maneja como 'private-order.{orderId}'
Broadcast::channel('order.{orderId}', function ($user, $orderId) {
    // Si $user es null, intentar obtenerlo del guard sanctum
    if (!$user) {
        $user = \Illuminate\Support\Facades\Auth::guard('sanctum')->user();
    }
    
    // Si aún no hay usuario, intentar con el guard por defecto
    if (!$user) {
        $user = \Illuminate\Support\Facades\Auth::user();
    }

    // Si aún no hay usuario, intentar con el guard web
    if (!$user) {
        $user = \Illuminate\Support\Facades\Auth::guard('web')->user();
    }

    // DEBUGGING: Log detallado
    Log::info('[Broadcasting Channel] Verificando acceso a orden', [
        'user_id' => $user ? $user->id : null,
        'user_email' => $user ? $user->email : null,
        'order_id' => $orderId,
        'timestamp' => now(),
    ]);

    // Si no hay usuario autenticado, denegar acceso
    if (!$user) {
        Log::warning('[Broadcasting Channel] ❌ Usuario NO autenticado', [
            'order_id' => $orderId,
        ]);
        return false;
    }

    // Verificar que el usuario tenga acceso a la orden
    // El usuario puede acceder si:
    // 1. Es el dueño de la orden
    // 2. Es admin o super_admin
    // 3. Es el delivery asignado a la orden
    $order = \App\Models\Order::find($orderId);
    
    if (!$order) {
        Log::warning('[Broadcasting Channel] ❌ Orden no encontrada', [
            'order_id' => $orderId,
            'user_id' => $user->id,
        ]);
        return false;
    }

    $hasAccess = false;
    
    // El dueño de la orden puede acceder
    if ($order->user_id === $user->id) {
        $hasAccess = true;
    }
    
    // Admin o super_admin pueden acceder
    if ($user->hasAnyRole(['admin', 'super_admin'])) {
        $hasAccess = true;
    }
    
    // El delivery asignado puede acceder
    if ($order->delivery_id === $user->id) {
        $hasAccess = true;
    }

    if ($hasAccess) {
        Log::info('[Broadcasting Channel] ✅ Acceso permitido', [
            'user_id' => $user->id,
            'order_id' => $orderId,
        ]);
        return true;
    }

    Log::warning('[Broadcasting Channel] ❌ Acceso denegado', [
        'user_id' => $user->id,
        'order_id' => $orderId,
        'order_user_id' => $order->user_id,
        'order_delivery_id' => $order->delivery_id,
    ]);
    
    return false;
});

// Canal privado para notificaciones de usuario (formato: private-user.{userId})
Broadcast::channel('private-user.{userId}', function ($user, $userId) {
    Log::info('[Broadcasting Auth] Verificando acceso a canal de usuario', [
        'user_id' => $user ? $user->id : null,
        'target_user_id' => $userId,
    ]);
    
    if ($user && $user->id == $userId) {
        Log::info('[Broadcasting Auth] ✅ Acceso permitido a canal de usuario');
        return true;
    }
    
    return false;
});

// Canal privado para notificaciones generales de admin (formato: private-admin.orders)
Broadcast::channel('private-admin.orders', function ($user) {
    Log::info('[Broadcasting Auth] Verificando acceso a canal de admin', [
        'user_id' => $user ? $user->id : null,
    ]);
    
    if ($user) {
        Log::info('[Broadcasting Auth] ✅ Acceso permitido a canal de admin (modo debug)');
        return true;
    }
    
    return false;
});
