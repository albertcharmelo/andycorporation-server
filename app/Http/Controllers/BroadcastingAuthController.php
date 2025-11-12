<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;

class BroadcastingAuthController extends Controller
{
    /**
     * Autenticar usuario para canales de broadcasting
     * 
     * Esta ruta maneja la autenticación de Pusher/Echo para canales privados
     * Valida que el usuario esté autenticado antes de permitir la suscripción
     */
    public function authenticate(Request $request)
    {
        // Log para debugging
        Log::info('[Broadcasting Auth] Solicitud de autenticación recibida', [
            'socket_id' => $request->input('socket_id'),
            'channel_name' => $request->input('channel_name'),
            'authenticated' => Auth::guard('sanctum')->check(),
            'user_id' => Auth::guard('sanctum')->id(),
            'has_token' => $request->bearerToken() ? true : false,
        ]);

        // Verificar que el usuario esté autenticado (usando Sanctum para API)
        if (!Auth::guard('sanctum')->check()) {
            Log::warning('[Broadcasting Auth] ❌ Usuario no autenticado');
            return response()->json([
                'message' => 'No autenticado'
            ], 403);
        }

        $user = Auth::guard('sanctum')->user();
       
        // Validar que tenemos los datos necesarios
        if (!$request->has('socket_id') || !$request->has('channel_name')) {
            Log::warning('[Broadcasting Auth] ❌ Faltan parámetros requeridos', [
                'has_socket_id' => $request->has('socket_id'),
                'has_channel_name' => $request->has('channel_name'),
            ]);
            return response()->json([
                'message' => 'Faltan parámetros requeridos'
            ], 400);
        }

        // Extraer el nombre del canal y validarlo
        $channelName = $request->input('channel_name');
        $socketId = $request->input('socket_id');

        // Validar que el canal sea privado
        if (!str_starts_with($channelName, 'private-')) {
            Log::warning('[Broadcasting Auth] ❌ Canal no es privado', [
                'channel_name' => $channelName,
            ]);
            return response()->json([
                'message' => 'Canal no autorizado'
            ], 403);
        }

        // IMPORTANTE: Establecer el usuario en el guard por defecto
        // para que Broadcast::auth() pueda acceder a él
        Auth::setUser($user);

        // Usar Laravel Broadcasting para generar la respuesta de autenticación
        // Esto maneja automáticamente la firma y la validación del canal
        try {
            Log::info('[Broadcasting Auth] ✅ Usuario autenticado, generando respuesta', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'channel_name' => $channelName,
            ]);

            // Broadcast::auth() ahora usará el usuario establecido arriba
            return Broadcast::auth($request);
        } catch (\Exception $e) {
            Log::error('[Broadcasting Auth] ❌ Error al autenticar', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'channel_name' => $channelName,
            ]);

            return response()->json([
                'message' => 'Error al autenticar',
                'error' => $e,
            ], 500);
        }
    }
}
