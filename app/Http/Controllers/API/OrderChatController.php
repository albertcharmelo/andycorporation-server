<?php

namespace App\Http\Controllers\API;

use App\Events\OrderMessageSent;
use App\Http\Controllers\Controller;
use App\Jobs\BroadcastOrderMessage;
use App\Models\Message;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
class OrderChatController extends Controller
{
    /**
     * Obtener mensajes del chat de una orden.
     * 
     * Reglas:
     * - Cliente/Admin: Ven todos los mensajes
     * - Delivery: Solo ve mensajes después de assigned_at
     *
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMessages(Request $request, $orderId)
    {
        try {
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado',
                ], 401);
            }
            
            $order = Order::findOrFail($orderId);
            
            // Determinar rol del usuario
            $userRole = $this->getUserRole($user, $order);
            
            // Verificar permisos
            if (!$this->canAccessOrder($user, $order, $userRole)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para ver este chat',
                ], 403);
            }

            // Construir query base
            $query = Message::forOrder($orderId)
                ->with('user:id,name,email,avatar');

            // Si es delivery, solo mostrar mensajes después de assigned_at
            if ($userRole === 'delivery' && $order->assigned_at) {
                $query->afterDate($order->assigned_at);
            }

            $messages = $query->get();

            return response()->json([
                'order_id' => $orderId,
                'messages' => $messages,
                'user_role' => $userRole,
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
     * Enviar mensaje en el chat.
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMessage(Request $request, $orderId)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }
        
        $request->validate([
            'message' => 'required|string|max:1000',
            'message_type' => 'nullable|in:text,image,file',
            'file' => 'nullable|file|max:10240', // 10MB max
        ]);

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($orderId);
            
            // Determinar rol
            $userRole = $this->getUserRole($user, $order);
            
            // Verificar permisos
            if (!$this->canSendMessage($user, $order, $userRole)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para enviar mensajes en este chat',
                ], 403);
            }

            // Validar que delivery solo puede enviar después de ser asignado
            if ($userRole === 'delivery' && !$order->assigned_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes estar asignado a esta orden para enviar mensajes',
                ], 403);
            }

            $messageType = $request->message_type ?? 'text';
            $filePath = null;

            // Manejar archivo si existe
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs("messages/{$orderId}", $fileName, 'public');
                
                // Actualizar message_type según tipo de archivo
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $messageType = 'image';
                } else {
                    $messageType = 'file';
                }
            }

            // Determinar si es mensaje de delivery
            $isDeliveryMessage = $userRole === 'delivery';

            $message = Message::create([
                'order_id' => $orderId,
                'user_id' => $user->id,
                'message' => $request->message,
                'message_type' => $messageType,
                'file_path' => $filePath,
                'is_delivery_message' => $isDeliveryMessage,
            ]);

            $message->load('user:id,name,email,avatar');

            DB::commit();

            // Disparar evento de broadcasting inmediatamente (sincrónico para tiempo real)
            try {
                \Log::info('Disparando evento OrderMessageSent para orden: ' . $orderId);
                event(new OrderMessageSent($message, $user, $order));
                \Log::info('Evento OrderMessageSent disparado exitosamente');
            } catch (\Exception $e) {
                \Log::error('Error al disparar evento OrderMessageSent: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Mensaje enviado exitosamente',
                'data' => $message,
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
            $order = Order::findOrFail($orderId);

            // Verificar permisos
            $userRole = $this->getUserRole($user, $order);
            if (!$this->canAccessOrder($user, $order, $userRole)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para esta acción',
                ], 403);
            }

            // Marcar como leídos los mensajes que NO fueron enviados por el usuario actual
            $query = Message::where('order_id', $orderId)
                ->where('user_id', '!=', $user->id)
                ->where('is_read', false);

            // Si es delivery, solo marcar mensajes después de assigned_at
            if ($userRole === 'delivery' && $order->assigned_at) {
                $query->afterDate($order->assigned_at);
            }

            $updatedCount = $query->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

            return response()->json([
                'message' => 'Mensajes marcados como leídos',
                'updated_count' => $updatedCount,
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
     * Obtener estadísticas del chat.
     *
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStats(Request $request, $orderId)
    {
        try {
            $order = Order::findOrFail($orderId);
            $user = $request->user();
            
            $userRole = $this->getUserRole($user, $order);
            if (!$this->canAccessOrder($user, $order, $userRole)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para ver estadísticas',
                ], 403);
            }

            $query = Message::where('order_id', $orderId);
            
            // Si es delivery, solo contar mensajes después de assigned_at
            if ($userRole === 'delivery' && $order->assigned_at) {
                $query->afterDate($order->assigned_at);
            }

            $totalMessages = (clone $query)->count();
            $unreadMessages = (clone $query)
                ->where('user_id', '!=', $user->id)
                ->where('is_read', false)
                ->count();
            
            $deliveryMessages = (clone $query)->deliveryMessages()->count();
            $preDeliveryMessages = Message::where('order_id', $orderId)
                ->preDeliveryMessages()
                ->count();
            
            $lastMessage = (clone $query)->orderBy('created_at', 'desc')->first();

            return response()->json([
                'stats' => [
                    'total_messages' => $totalMessages,
                    'unread_messages' => $unreadMessages,
                    'delivery_messages' => $deliveryMessages,
                    'pre_delivery_messages' => $preDeliveryMessages,
                    'last_message_at' => $lastMessage ? $lastMessage->created_at->toIso8601String() : null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener archivo adjunto de un mensaje.
     *
     * @param int $orderId
     * @param int $messageId
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function getAttachment(Request $request, $orderId, $messageId)
    {
        try {
            $message = Message::where('order_id', $orderId)
                ->findOrFail($messageId);

            if (!$message->file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este mensaje no tiene archivo adjunto',
                ], 404);
            }

            $user = $request->user();
            $order = $message->order;
            
            // Verificar permisos
            $userRole = $this->getUserRole($user, $order);
            if (!$this->canAccessOrder($user, $order, $userRole)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para ver este archivo',
                ], 403);
            }

            if (!Storage::disk('public')->exists($message->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo no existe',
                ], 404);
            }

            $filePath = Storage::disk('public')->path($message->file_path);
            $mimeType = Storage::disk('public')->mimeType($message->file_path);

            return response()->file($filePath, [
                'Content-Type' => $mimeType,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener archivo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Determinar el rol del usuario en relación a la orden.
     */
    private function getUserRole($user, $order): string
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

    /**
     * Verificar si el usuario puede acceder a la orden.
     */
    private function canAccessOrder($user, $order, $userRole): bool
    {
        if ($userRole === 'admin') {
            return true;
        }
        
        if ($userRole === 'client' && $order->user_id === $user->id) {
            return true;
        }
        
        if ($userRole === 'delivery' && $order->delivery_id === $user->id && $order->assigned_at) {
            return true;
        }
        
        return false;
    }

    /**
     * Verificar si el usuario puede enviar mensajes.
     */
    private function canSendMessage($user, $order, $userRole): bool
    {
        if (!$this->canAccessOrder($user, $order, $userRole)) {
            return false;
        }
        
        // Delivery solo puede enviar después de ser asignado
        if ($userRole === 'delivery' && !$order->assigned_at) {
            return false;
        }
        
        return true;
    }
}
