<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Events\NotificationRead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Listar notificaciones del usuario con paginación
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $perPage = $request->get('per_page', 20);
        $type = $request->get('type');
        $isRead = $request->get('is_read');
        
        $query = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');
        
        // Filtros opcionales
        if ($type) {
            $query->where('type', $type);
        }
        
        if ($isRead !== null) {
            $query->where('is_read', filter_var($isRead, FILTER_VALIDATE_BOOLEAN));
        }
        
        $notifications = $query->paginate($perPage);
        
        return response()->json([
            'data' => $notifications->items(),
            'current_page' => $notifications->currentPage(),
            'last_page' => $notifications->lastPage(),
            'per_page' => $notifications->perPage(),
            'total' => $notifications->total(),
        ]);
    }

    /**
     * Obtener contador de notificaciones no leídas
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        
        $count = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
        
        return response()->json([
            'unread_count' => $count,
        ]);
    }

    /**
     * Marcar notificación como leída
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        
        $notification = Notification::where('user_id', $user->id)
            ->where('id', $id)
            ->first();
        
        if (!$notification) {
            return response()->json([
                'message' => 'Notificación no encontrada',
            ], 404);
        }
        
        if ($notification->is_read) {
            return response()->json([
                'message' => 'La notificación ya está marcada como leída',
                'notification' => $notification,
            ]);
        }
        
        $notification->markAsRead();
        
        // Emitir evento Pusher
        try {
            broadcast(new NotificationRead($notification));
        } catch (\Exception $e) {
            Log::error('Error al emitir evento NotificationRead', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
        
        return response()->json([
            'message' => 'Notificación marcada como leída',
            'notification' => $notification,
        ]);
    }

    /**
     * Marcar todas las notificaciones como leídas
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        
        $updated = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        
        return response()->json([
            'message' => "{$updated} notificaciones marcadas como leídas",
            'updated_count' => $updated,
        ]);
    }

    /**
     * Obtener detalle de una notificación
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        
        $notification = Notification::where('user_id', $user->id)
            ->where('id', $id)
            ->first();
        
        if (!$notification) {
            return response()->json([
                'message' => 'Notificación no encontrada',
            ], 404);
        }
        
        return response()->json([
            'notification' => $notification,
        ]);
    }
}
