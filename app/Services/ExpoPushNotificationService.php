<?php

namespace App\Services;

use App\Models\PushToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushNotificationService
{
    /**
     * URL de la API de Expo Push Notifications
     */
    private const EXPO_PUSH_API_URL = 'https://exp.host/--/api/v2/push/send';

    /**
     * Enviar notificación push a un token específico
     *
     * @param string $expoPushToken
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendNotification(string $expoPushToken, string $title, string $body, array $data = []): bool
    {
        try {
            $response = Http::timeout(10)->post(self::EXPO_PUSH_API_URL, [
                'to' => $expoPushToken,
                'sound' => 'default',
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'priority' => 'high',
                'channelId' => 'default',
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                // Verificar si hay errores en la respuesta
                if (isset($responseData['data']) && is_array($responseData['data'])) {
                    foreach ($responseData['data'] as $result) {
                        if (isset($result['status']) && $result['status'] === 'error') {
                            $error = $result['message'] ?? 'Error desconocido';
                            
                            // Si el token es inválido, marcarlo como inactivo
                            if (str_contains($error, 'InvalidExpoPushToken') || str_contains($error, 'DeviceNotRegistered')) {
                                $this->markTokenAsInactive($expoPushToken);
                            }
                            
                            Log::warning('Error al enviar notificación push', [
                                'token' => substr($expoPushToken, 0, 20) . '...',
                                'error' => $error,
                            ]);
                            
                            return false;
                        }
                    }
                }

                Log::info('Notificación push enviada exitosamente', [
                    'token' => substr($expoPushToken, 0, 20) . '...',
                    'title' => $title,
                ]);

                return true;
            }

            Log::error('Error al enviar notificación push - HTTP Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Excepción al enviar notificación push', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Enviar notificación push a todos los tokens activos de un usuario
     *
     * @param int $userId
     * @param string $title
     * @param string $body
     * @param array $data
     * @return int Número de notificaciones enviadas exitosamente
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): int
    {
        $tokens = PushToken::where('user_id', $userId)
            ->active()
            ->get();

        if ($tokens->isEmpty()) {
            Log::info('Usuario no tiene tokens push registrados', [
                'user_id' => $userId,
            ]);
            return 0;
        }

        $successCount = 0;
        $tokensToSend = [];

        // Preparar array de notificaciones para envío en batch
        foreach ($tokens as $token) {
            $tokensToSend[] = [
                'to' => $token->expo_push_token,
                'sound' => 'default',
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'priority' => 'high',
                'channelId' => 'default',
            ];
        }

        // Enviar todas las notificaciones en una sola petición (más eficiente)
        try {
            $response = Http::timeout(10)->post(self::EXPO_PUSH_API_URL, $tokensToSend);

            if ($response->successful()) {
                $responseData = $response->json();
                
                if (isset($responseData['data']) && is_array($responseData['data'])) {
                    foreach ($responseData['data'] as $index => $result) {
                        if (isset($result['status'])) {
                            if ($result['status'] === 'ok') {
                                $successCount++;
                            } elseif ($result['status'] === 'error') {
                                $error = $result['message'] ?? 'Error desconocido';
                                $token = $tokens[$index] ?? null;
                                
                                // Si el token es inválido, marcarlo como inactivo
                                if ($token && (str_contains($error, 'InvalidExpoPushToken') || str_contains($error, 'DeviceNotRegistered'))) {
                                    $this->markTokenAsInactive($token->expo_push_token);
                                }
                                
                                Log::warning('Error al enviar notificación push a token específico', [
                                    'token' => $token ? substr($token->expo_push_token, 0, 20) . '...' : 'unknown',
                                    'error' => $error,
                                ]);
                            }
                        }
                    }
                }

                Log::info('Notificaciones push enviadas a usuario', [
                    'user_id' => $userId,
                    'total_tokens' => $tokens->count(),
                    'successful' => $successCount,
                    'title' => $title,
                ]);
            } else {
                Log::error('Error al enviar notificaciones push - HTTP Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Excepción al enviar notificaciones push', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        return $successCount;
    }

    /**
     * Marcar un token como inactivo (cuando es inválido)
     *
     * @param string $expoPushToken
     * @return void
     */
    private function markTokenAsInactive(string $expoPushToken): void
    {
        try {
            PushToken::where('expo_push_token', $expoPushToken)
                ->update(['is_active' => false]);

            Log::info('Token push marcado como inactivo', [
                'token' => substr($expoPushToken, 0, 20) . '...',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al marcar token como inactivo', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
