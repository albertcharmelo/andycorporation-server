<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * Obtener todos los mensajes de una orden.
     *
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMessages($orderId)
    {
        try {
            $order = Order::findOrFail($orderId);

            $messages = Message::forOrder($orderId)
                ->with('user:id,name,email,avatar')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $messages,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener mensajes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enviar un mensaje en el chat de una orden.
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMessage(Request $request, $orderId)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($orderId);

            // Verificar permisos: admin o el cliente de la orden
            $user = $request->user();
            $isAdmin = $user->hasAnyRole(['admin', 'super_admin']);
            $isOrderOwner = $order->user_id === $user->id;

            if (!$isAdmin && !$isOrderOwner) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para enviar mensajes en este chat',
                ], 403);
            }

            $message = Message::create([
                'order_id' => $orderId,
                'user_id' => $user->id,
                'message' => $request->message,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Mensaje enviado',
                'data' => $message->load('user:id,name,email,avatar'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar mensaje',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marcar mensajes como leídos.
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead(Request $request, $orderId)
    {
        try {
            $user = $request->user();

            // Marcar como leídos los mensajes que NO fueron enviados por el usuario actual
            Message::where('order_id', $orderId)
                ->where('user_id', '!=', $user->id)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Mensajes marcados como leídos',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar mensajes como leídos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener cantidad de mensajes no leídos de una orden.
     *
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUnreadCount(Request $request, $orderId)
    {
        try {
            $user = $request->user();

            $unreadCount = Message::where('order_id', $orderId)
                ->where('user_id', '!=', $user->id)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $unreadCount,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener mensajes no leídos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
