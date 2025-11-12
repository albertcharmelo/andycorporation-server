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

    // DEBUGGING: Log detallado
    Log::info('[Broadcasting Auth] Verificando acceso', [
        'user_id' => $user ? $user->id : null,
        'user_email' => $user ? $user->email : null,
        'order_id' => $orderId,
        'timestamp' => now(),
        'user_from_param' => $user !== null ? 'yes' : 'no',
        'user_from_sanctum' => \Illuminate\Support\Facades\Auth::guard('sanctum')->check(),
    ]);

    // TEMPORAL: Permitir acceso a todos los usuarios autenticados para debugging
    // Una vez que funcione, descomentar la lógica completa abajo
    if ($user) {
        Log::info('[Broadcasting Auth] ✅ Usuario autenticado - ACCESO PERMITIDO (modo debug)', [
            'user_id' => $user->id,
            'order_id' => $orderId,
        ]);
         return (bool) $user;
    }

    Log::warning('[Broadcasting Auth] ❌ Usuario NO autenticado', [
        'order_id' => $orderId,
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
