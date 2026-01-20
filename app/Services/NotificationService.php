<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Events\NotificationCreated;
use App\Services\ExpoPushNotificationService;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $pushService;

    public function __construct(ExpoPushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    /**
     * Crear una notificación para un usuario
     *
     * @param int $userId
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array|null $data
     * @param bool $sendPush
     * @return Notification
     */
    public function create(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?array $data = null,
        bool $sendPush = true
    ): Notification {
        // Crear notificación en la base de datos
        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'is_read' => false,
        ]);

        // Emitir evento Pusher
        try {
            broadcast(new NotificationCreated($notification));
            Log::info('Evento NotificationCreated emitido', [
                'notification_id' => $notification->id,
                'user_id' => $userId,
                'type' => $type,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al emitir evento NotificationCreated', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Enviar push notification si está habilitado
        if ($sendPush) {
            try {
                $this->sendPushNotification($userId, $title, $message, $data);
            } catch (\Exception $e) {
                Log::error('Error al enviar push notification', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $notification;
    }

    /**
     * Enviar notificación push al usuario
     *
     * @param int $userId
     * @param string $title
     * @param string $body
     * @param array|null $data
     * @return void
     */
    protected function sendPushNotification(int $userId, string $title, string $body, ?array $data = null): void
    {
        $user = User::with('pushTokens')->find($userId);
        
        if (!$user || $user->pushTokens->isEmpty()) {
            return;
        }

        $pushData = array_merge($data ?? [], [
            'type' => 'notification',
        ]);

        $this->pushService->sendToUser($userId, $title, $body, $pushData);
    }

    /**
     * Crear notificaciones para múltiples usuarios
     *
     * @param array $userIds
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array|null $data
     * @param bool $sendPush
     * @return array
     */
    public function createForUsers(
        array $userIds,
        string $type,
        string $title,
        string $message,
        ?array $data = null,
        bool $sendPush = true
    ): array {
        $notifications = [];

        foreach ($userIds as $userId) {
            $notifications[] = $this->create($userId, $type, $title, $message, $data, $sendPush);
        }

        return $notifications;
    }
}
