<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\SendOrderMessage;
use App\Models\Message;
use App\Models\Order;
use App\Events\OrderMessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
     * El procesamiento se realiza de forma asíncrona mediante colas
     * para evitar congestionar la aplicación.
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

        try {
            // Validación básica de permisos antes de despachar el job
            $order = Order::findOrFail($orderId);
            $userRole = $this->getUserRole($user, $order);
            
            // Verificar permisos básicos
            if (!$this->canAccessOrder($user, $order, $userRole)) {
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
            $tempFilePath = null;
            $originalFileName = null;

            // Guardar archivo temporalmente si existe
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $originalFileName = $file->getClientOriginalName();
                
                // Guardar en ubicación temporal (directorio temp)
                $tempFileName = 'temp_' . \Illuminate\Support\Str::uuid() . '.' . $file->getClientOriginalExtension();
                $tempFilePath = $file->storeAs('temp/messages', $tempFileName, 'public');
                
                // Detectar tipo de mensaje según tipo de archivo
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $messageType = 'image';
                } else {
                    $messageType = 'file';
                }
            }

            // Verificar si estamos en modo sync (desarrollo) o async (producción)
            $queueConnection = config('queue.default');
            $isSync = $queueConnection === 'sync';
            
            Log::info('OrderChatController: Enviando mensaje', [
                'order_id' => $orderId,
                'user_id' => $user->id,
                'queue_connection' => $queueConnection,
                'is_sync' => $isSync,
            ]);

            // Si estamos en modo sync, ejecutar el job inmediatamente y esperar el resultado
            // Si estamos en modo async, despachar el job normalmente
            if ($isSync) {
                // En modo sync, ejecutar el job inmediatamente para que el evento se emita de inmediato
                try {
                    $job = new SendOrderMessage(
                        $orderId,
                        $user->id,
                        $request->message,
                        $messageType,
                        $tempFilePath,
                        $originalFileName
                    );
                    $job->handle();
                    
                    Log::info('OrderChatController: Job ejecutado en modo sync, evento debería estar emitido');
                    
                    return response()->json([
                        'message' => 'Mensaje enviado exitosamente',
                        'status' => 'sent',
                    ], 201);
                } catch (\Exception $e) {
                    Log::error('OrderChatController: Error al ejecutar job en modo sync', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    // Fallback: despachar el job normalmente
                    SendOrderMessage::dispatch(
                        $orderId,
                        $user->id,
                        $request->message,
                        $messageType,
                        $tempFilePath,
                        $originalFileName
                    );
                }
            } else {
                // Modo async: despachar el job normalmente
                SendOrderMessage::dispatch(
                    $orderId,
                    $user->id,
                    $request->message,
                    $messageType,
                    $tempFilePath,
                    $originalFileName
                );

                Log::info('OrderChatController: Job despachado en modo async para orden: ' . $orderId . ' por usuario: ' . $user->id);
            }

            // Responder inmediatamente mientras el job se procesa en background
            // El mensaje real llegará vía Pusher cuando el job termine de procesarse
            return response()->json([
                'message' => 'Mensaje en proceso de envío',
                'status' => 'processing',
                // Nota: 'data' no está disponible aquí porque el mensaje se procesa en background
                // El mensaje real llegará vía evento Pusher cuando el job termine
            ], 202); // 202 Accepted - indica que la solicitud fue aceptada pero aún no procesada

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada',
            ], 404);
        } catch (\Exception $e) {
            // Limpiar archivo temporal si hubo error antes de despachar el job
            if (isset($tempFilePath) && $tempFilePath && Storage::disk('public')->exists($tempFilePath)) {
                try {
                    Storage::disk('public')->delete($tempFilePath);
                } catch (\Exception $deleteError) {
                    \Log::warning('No se pudo eliminar archivo temporal después del error: ' . $deleteError->getMessage());
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar solicitud de envío',
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
