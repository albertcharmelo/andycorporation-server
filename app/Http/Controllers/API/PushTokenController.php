<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PushTokenController extends Controller
{
    /**
     * Registrar o actualizar token push del usuario
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $request->validate([
            'expo_push_token' => 'required|string',
            'device_id' => 'nullable|string|max:255',
            'platform' => 'nullable|in:ios,android',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado',
            ], 401);
        }

        // Validar formato del token Expo
        if (!PushToken::isValidExpoToken($request->expo_push_token)) {
            return response()->json([
                'success' => false,
                'message' => 'Formato de token inválido',
            ], 400);
        }

        try {
            // Buscar si ya existe un token con el mismo expo_push_token
            $existingToken = PushToken::where('expo_push_token', $request->expo_push_token)->first();

            if ($existingToken) {
                // Si el token pertenece a otro usuario, actualizarlo
                if ($existingToken->user_id !== $user->id) {
                    $existingToken->update([
                        'user_id' => $user->id,
                        'device_id' => $request->device_id,
                        'platform' => $request->platform,
                        'is_active' => true,
                    ]);

                    Log::info('PushToken actualizado para usuario diferente', [
                        'old_user_id' => $existingToken->user_id,
                        'new_user_id' => $user->id,
                        'token' => substr($request->expo_push_token, 0, 20) . '...',
                    ]);
                } else {
                    // Si ya pertenece al mismo usuario, solo actualizar campos opcionales
                    $existingToken->update([
                        'device_id' => $request->device_id ?? $existingToken->device_id,
                        'platform' => $request->platform ?? $existingToken->platform,
                        'is_active' => true,
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Token actualizado exitosamente',
                    'token' => $existingToken,
                ]);
            }

            // Crear nuevo token
            $pushToken = PushToken::create([
                'user_id' => $user->id,
                'expo_push_token' => $request->expo_push_token,
                'device_id' => $request->device_id,
                'platform' => $request->platform,
                'is_active' => true,
            ]);

            Log::info('PushToken registrado exitosamente', [
                'user_id' => $user->id,
                'token' => substr($request->expo_push_token, 0, 20) . '...',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token registrado exitosamente',
                'token' => $pushToken,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al registrar PushToken', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar token',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar token push (logout o desinstalar app)
     *
     * @param Request $request
     * @param string $token
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request, string $token)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado',
            ], 401);
        }

        try {
            $pushToken = PushToken::where('expo_push_token', $token)
                ->where('user_id', $user->id)
                ->first();

            if (!$pushToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token no encontrado',
                ], 404);
            }

            $pushToken->delete();

            Log::info('PushToken eliminado', [
                'user_id' => $user->id,
                'token' => substr($token, 0, 20) . '...',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token eliminado exitosamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar PushToken', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar token',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
