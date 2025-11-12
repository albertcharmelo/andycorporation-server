<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use App\Events\OrderMessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;

class TestChatController extends Controller
{
    /**
     * Ruta de prueba para enviar mensajes a una orden
     * 
     * Ejemplo de uso:
     * GET /api/test/send-message/{orderId}?message=Hola desde test&user_id=1
     * 
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendTestMessage(Request $request, $orderId)
    {
        try {
            // Validar que la orden existe
            $order = Order::findOrFail($orderId);
            
            // Obtener el mensaje del query parameter o usar uno por defecto
            $messageText = $request->query('message', 'Mensaje de prueba desde test endpoint');
            
            // Obtener el user_id del query parameter o usar el primero admin disponible
            $userId = $request->query('user_id');
            
            if (!$userId) {
                // Buscar un admin o el primer usuario disponible
                $user = User::whereHas('roles', function ($query) {
                    $query->whereIn('name', ['admin', 'super_admin']);
                })->first();
                
                if (!$user) {
                    $user = User::first();
                }
                
                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No hay usuarios disponibles para enviar el mensaje',
                    ], 404);
                }
                
                $userId = $user->id;
            } else {
                $user = User::findOrFail($userId);
            }

            Log::info('[Test Chat] Enviando mensaje de prueba', [
                'order_id' => $orderId,
                'user_id' => $userId,
                'message' => $messageText,
                'broadcast_default' => config('broadcasting.default'),
                'pusher_config' => [
                    'app_id' => config('broadcasting.connections.pusher.app_id'),
                    'key' => config('broadcasting.connections.pusher.key'),
                    'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                ],
            ]);

            // Crear el mensaje
            $message = Message::create([
                'order_id' => $orderId,
                'user_id' => $userId,
                'message' => $messageText,
                'message_type' => 'text',
            ]);

            // Cargar relaciones antes de emitir el evento
            $message->load('user:id,name,email,avatar');
            $order->refresh();

            // Emitir el evento de broadcasting
            try {
                Log::info('[Test Chat] 📡 Emitiendo evento OrderMessageSent...', [
                    'message_id' => $message->id,
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'channel' => 'private-order.' . $order->id,
                    'event_name' => 'order.message.sent',
                    'broadcast_connection' => config('broadcasting.default'),
                ]);
                
                // Crear el evento
                $event = new OrderMessageSent($message, $user, $order);
                
                // Verificar configuración de broadcasting
                $broadcastDriver = config('broadcasting.default');
                if ($broadcastDriver === 'null') {
                    Log::warning('[Test Chat] ⚠️ BROADCAST_CONNECTION está en "null", cambiando a "pusher" temporalmente');
                    config(['broadcasting.default' => 'pusher']);
                }
                
                // Usar broadcast() para forzar el broadcasting inmediatamente
                $result = broadcast($event);
                
                Log::info('[Test Chat] ✅ Evento OrderMessageSent emitido exitosamente', [
                    'broadcast_result_type' => get_class($result),
                    'broadcast_driver' => config('broadcasting.default'),
                ]);
            } catch (\Exception $e) {
                Log::error('[Test Chat] ❌ Error al emitir evento:', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Mensaje de prueba enviado exitosamente',
                'data' => [
                    'message' => $message,
                    'order_id' => $orderId,
                    'user' => $user->only(['id', 'name', 'email']),
                    'pusher_channel' => 'private-order.' . $orderId,
                    'pusher_event' => 'order.message.sent',
                    'broadcast_config' => [
                        'default' => config('broadcasting.default'),
                        'pusher_configured' => config('broadcasting.connections.pusher.key') !== null,
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('[Test Chat] ❌ Error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar mensaje de prueba',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
