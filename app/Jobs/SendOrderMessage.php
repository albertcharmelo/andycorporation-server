<?php

namespace App\Jobs;

use App\Events\OrderMessageSent;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use App\Services\ExpoPushNotificationService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SendOrderMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * El número de veces que se puede intentar el job.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * El número de segundos que se debe esperar antes de reintentar el job.
     *
     * @var int
     */
    public $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $orderId,
        public int $userId,
        public string $messageText,
        public ?string $messageType = 'text',
        public ?string $tempFilePath = null,
        public ?string $originalFileName = null
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::beginTransaction();
        try {
            // Obtener la orden y el usuario
            $order = Order::findOrFail($this->orderId);
            $user = User::findOrFail($this->userId);

            // Validar permisos
            $userRole = $this->getUserRole($user, $order);
            if (!$this->canSendMessage($user, $order, $userRole)) {
                Log::warning("SendOrderMessage: Usuario {$user->id} no tiene permisos para enviar mensaje en orden {$this->orderId}");
                throw new \Exception('No tienes permisos para enviar mensajes en este chat');
            }

            // Validar que delivery solo puede enviar después de ser asignado
            if ($userRole === 'delivery' && !$order->assigned_at) {
                Log::warning("SendOrderMessage: Delivery {$user->id} intentó enviar mensaje antes de ser asignado a orden {$this->orderId}");
                throw new \Exception('Debes estar asignado a esta orden para enviar mensajes');
            }

            // Manejar archivo si existe
            $filePath = null;
            if ($this->tempFilePath && Storage::disk('public')->exists($this->tempFilePath)) {
                // Mover archivo de ubicación temporal a ubicación final
                $fileName = Str::uuid() . '.' . pathinfo($this->originalFileName ?? $this->tempFilePath, PATHINFO_EXTENSION);
                $finalPath = "messages/{$this->orderId}/{$fileName}";
                
                // Crear directorio si no existe
                $directory = dirname($finalPath);
                if (!Storage::disk('public')->exists($directory)) {
                    Storage::disk('public')->makeDirectory($directory);
                }

                // Mover archivo
                Storage::disk('public')->move($this->tempFilePath, $finalPath);
                $filePath = $finalPath;

                // Actualizar message_type según tipo de archivo si no se especificó
                if ($this->messageType === 'text') {
                    $mimeType = Storage::disk('public')->mimeType($finalPath);
                    if (str_starts_with($mimeType, 'image/')) {
                        $this->messageType = 'image';
                    } else {
                        $this->messageType = 'file';
                    }
                }
            }

            // Determinar si es mensaje de delivery
            $isDeliveryMessage = $userRole === 'delivery';

            // Crear mensaje
            $message = Message::create([
                'order_id' => $this->orderId,
                'user_id' => $this->userId,
                'message' => $this->messageText,
                'message_type' => $this->messageType,
                'file_path' => $filePath,
                'is_delivery_message' => $isDeliveryMessage,
            ]);

            $message->load('user:id,name,email,avatar');

            DB::commit();

            // Disparar evento de broadcasting
            try {
                Log::info('SendOrderMessage: Disparando evento OrderMessageSent para orden: ' . $this->orderId, [
                    'broadcast_connection' => config('broadcasting.default'),
                    'pusher_configured' => config('broadcasting.connections.pusher.key') !== null,
                ]);
                
                // Verificar configuración de broadcasting antes de emitir
                $broadcastDriver = config('broadcasting.default');
                if ($broadcastDriver === 'null') {
                    Log::warning('SendOrderMessage: ⚠️ BROADCAST_CONNECTION está en "null", cambiando a "pusher" temporalmente');
                    config(['broadcasting.default' => 'pusher']);
                }
                
                // Usar broadcast() en lugar de event() para asegurar que se envíe a Pusher
                $event = new OrderMessageSent($message, $user, $order);
             
                
                // Enviar el evento directamente usando el broadcaster
                try {
                    // Obtener el broadcaster de Pusher
                    $broadcaster = app(\Illuminate\Contracts\Broadcasting\Broadcaster::class);
                    
                    // Obtener los canales y datos
                    $channels = $event->broadcastOn();
                    $eventName = $event->broadcastAs();
                    $payload = $event->broadcastWith();
                    
                    Log::info('SendOrderMessage: Enviando evento directamente al broadcaster', [
                        'channels_count' => count($channels),
                        'event_name' => $eventName,
                        'has_payload' => !empty($payload),
                    ]);
                    
                    // Enviar a cada canal
                    foreach ($channels as $channel) {
                        try {
                            $broadcaster->broadcast([$channel], $eventName, $payload);
                            Log::info('SendOrderMessage: ✅ Evento enviado al canal', [
                                'channel' => is_string($channel) ? $channel : get_class($channel),
                                'event' => $eventName,
                            ]);
                        } catch (\Exception $channelException) {
                            Log::error('SendOrderMessage: ❌ Error al enviar al canal', [
                                'channel' => is_string($channel) ? $channel : get_class($channel),
                                'error' => $channelException->getMessage(),
                            ]);
                        }
                    }
                    
                    Log::info('SendOrderMessage: Evento OrderMessageSent enviado exitosamente', [
                        'broadcast_connection' => config('broadcasting.default'),
                        'pusher_key' => config('broadcasting.connections.pusher.key'),
                        'pusher_app_id' => config('broadcasting.connections.pusher.app_id'),
                        'pusher_cluster' => config('broadcasting.connections.pusher.options.cluster'),
                    ]);

                    // Crear notificaciones en DB y enviar push a los otros usuarios de la orden (no al que envió el mensaje)
                    try {
                        $notificationService = app(NotificationService::class);
                        $orderNumber = 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT);
                        
                        // Truncar mensaje para la notificación (máximo 100 caracteres)
                        $messagePreview = mb_strlen($this->messageText) > 100 
                            ? mb_substr($this->messageText, 0, 100) . '...' 
                            : $this->messageText;
                        
                        // Determinar a quién enviar la notificación
                        // Si el mensaje es del cliente, notificar a admin/delivery
                        // Si el mensaje es de admin/delivery, notificar al cliente
                        $usersToNotify = [];
                        
                        if ($userRole === 'client') {
                            // Notificar a delivery si está asignado
                            if ($order->delivery_id && $order->delivery_id !== $this->userId) {
                                $usersToNotify[] = [
                                    'id' => $order->delivery_id,
                                    'role' => 'delivery',
                                ];
                            }
                            // Notificar a admins
                            $admins = User::role(['admin', 'super_admin'])->get();
                            foreach ($admins as $admin) {
                                if ($admin->id !== $this->userId) {
                                    $usersToNotify[] = [
                                        'id' => $admin->id,
                                        'role' => 'admin',
                                    ];
                                }
                            }
                        } elseif ($userRole === 'delivery') {
                            // Si es delivery, notificar al cliente
                            if ($order->user_id && $order->user_id !== $this->userId) {
                                $usersToNotify[] = [
                                    'id' => $order->user_id,
                                    'role' => 'client',
                                ];
                            }
                            // También notificar a admins
                            $admins = User::role(['admin', 'super_admin'])->get();
                            foreach ($admins as $admin) {
                                if ($admin->id !== $this->userId) {
                                    $usersToNotify[] = [
                                        'id' => $admin->id,
                                        'role' => 'admin',
                                    ];
                                }
                            }
                        } else {
                            // Si es admin, notificar al cliente y delivery
                            if ($order->user_id && $order->user_id !== $this->userId) {
                                $usersToNotify[] = [
                                    'id' => $order->user_id,
                                    'role' => 'client',
                                ];
                            }
                            if ($order->delivery_id && $order->delivery_id !== $this->userId) {
                                $usersToNotify[] = [
                                    'id' => $order->delivery_id,
                                    'role' => 'delivery',
                                ];
                            }
                        }
                        
                        // Crear notificaciones en DB y enviar push
                        foreach ($usersToNotify as $userToNotify) {
                            if ($userToNotify['id'] !== $this->userId) {
                                $senderName = $user->name;
                                $title = 'Nuevo mensaje';
                                $body = "{$senderName}: {$messagePreview}";
                                
                                $notificationService->create(
                                    $userToNotify['id'],
                                    'message_received',
                                    $title,
                                    $body,
                                    [
                                        'order_id' => $order->id,
                                        'order_number' => $orderNumber,
                                        'message_id' => $message->id,
                                        'sender_id' => $this->userId,
                                        'sender_name' => $senderName,
                                        'message_preview' => $messagePreview,
                                    ],
                                    true // Enviar push notification
                                );
                                
                                Log::info('SendOrderMessage: Notificación creada', [
                                    'to_user_id' => $userToNotify['id'],
                                    'to_role' => $userToNotify['role'],
                                    'order_id' => $this->orderId,
                                    'from_user_id' => $this->userId,
                                ]);
                            }
                        }
                    } catch (\Exception $notificationException) {
                        // No fallar el job si la notificación falla
                        Log::error('SendOrderMessage: Error al crear notificaciones', [
                            'error' => $notificationException->getMessage(),
                            'order_id' => $this->orderId,
                            'trace' => $notificationException->getTraceAsString(),
                        ]);
                    }
                } catch (\Exception $broadcastException) {
                    Log::error('SendOrderMessage: Error al enviar evento al broadcaster', [
                        'error' => $broadcastException->getMessage(),
                        'trace' => $broadcastException->getTraceAsString(),
                    ]);
                    // No lanzar excepción, el mensaje ya está guardado
                }
            } catch (\Exception $e) {
                Log::error('SendOrderMessage: Error al disparar evento OrderMessageSent', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                // No lanzar excepción aquí, el mensaje ya está guardado
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Limpiar archivo temporal si existe y hubo error
            if ($this->tempFilePath && Storage::disk('public')->exists($this->tempFilePath)) {
                try {
                    Storage::disk('public')->delete($this->tempFilePath);
                } catch (\Exception $deleteError) {
                    Log::warning('SendOrderMessage: No se pudo eliminar archivo temporal: ' . $deleteError->getMessage());
                }
            }

            Log::error('SendOrderMessage: Error al procesar mensaje: ' . $e->getMessage(), [
                'order_id' => $this->orderId,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendOrderMessage: Job falló después de todos los reintentos', [
            'order_id' => $this->orderId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);

        // Limpiar archivo temporal si existe
        if ($this->tempFilePath && Storage::disk('public')->exists($this->tempFilePath)) {
            try {
                Storage::disk('public')->delete($this->tempFilePath);
            } catch (\Exception $deleteError) {
                Log::warning('SendOrderMessage: No se pudo eliminar archivo temporal después del fallo: ' . $deleteError->getMessage());
            }
        }
    }

    /**
     * Determinar el rol del usuario en relación a la orden.
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

    /**
     * Verificar si el usuario puede enviar mensajes.
     */
    private function canSendMessage(User $user, Order $order, string $userRole): bool
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
}
