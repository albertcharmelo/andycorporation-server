<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Message;
use App\Models\User;
use App\Events\OrderMessageSent;
use App\Services\ExpoPushNotificationService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    /**
     * Obtener todas las órdenes con filtros y paginación.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 20);
            $status = $request->get('status', 'all');
            $paymentMethod = $request->get('payment_method');
            $search = $request->get('search');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

            $orders = Order::with([
                'user:id,name,email,tel', 
                'delivery:id,name,email,tel',
                'address', 
                'items.product:id,name', 
                'paymentProof'
            ])
                ->byStatus($status)
                ->byPaymentMethod($paymentMethod)
                ->search($search)
                ->byDateRange($dateFrom, $dateTo)
                ->recent()
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $orders,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las órdenes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de órdenes para el dashboard.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(Request $request)
    {
        try {
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

            // Construir query base para estadísticas
            $baseQuery = Order::query();
            
            // Aplicar filtro de fecha si existe
            if ($dateFrom) {
                $baseQuery->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $baseQuery->whereDate('created_at', '<=', $dateTo);
            }

            $stats = [
                'total_orders' => (clone $baseQuery)->count(),
                'pending_payment' => (clone $baseQuery)->where('status', 'pending_payment')->count(),
                'paid' => (clone $baseQuery)->where('status', 'paid')->count(),
                'shipped' => (clone $baseQuery)->where('status', 'shipped')->count(),
                'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
                'cancelled' => (clone $baseQuery)->where('status', 'cancelled')->count(),
                'refunded' => (clone $baseQuery)->where('status', 'refunded')->count(),
                'total_revenue' => (clone $baseQuery)->whereIn('status', ['paid', 'shipped', 'completed'])->sum('total'),
                'pending_revenue' => (clone $baseQuery)->where('status', 'pending_payment')->sum('total'),
            ];

            // Órdenes por método de pago (con filtro de fecha)
            $paymentMethodsQuery = Order::select('payment_method', DB::raw('count(*) as count'));
            if ($dateFrom) {
                $paymentMethodsQuery->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $paymentMethodsQuery->whereDate('created_at', '<=', $dateTo);
            }
            $paymentMethods = $paymentMethodsQuery->groupBy('payment_method')->get();

            // Determinar rango de fechas para últimos días
            if ($dateFrom && $dateTo) {
                $from = \Carbon\Carbon::parse($dateFrom);
                $to = \Carbon\Carbon::parse($dateTo);
                $daysDiff = $from->diffInDays($to);
                
                // Si el rango es mayor a 90 días, agrupar por meses
                if ($daysDiff > 90) {
                    $last7Days = Order::select(
                        DB::raw('DATE_FORMAT(created_at, "%Y-%m") as date'),
                        DB::raw('count(*) as count'),
                        DB::raw('sum(total) as revenue')
                    )
                        ->whereDate('created_at', '>=', $dateFrom)
                        ->whereDate('created_at', '<=', $dateTo)
                        ->groupBy('date')
                        ->orderBy('date', 'asc')
                        ->get();
                } else {
                    // Mostrar por días
                    $last7Days = Order::select(
                        DB::raw('DATE(created_at) as date'),
                        DB::raw('count(*) as count'),
                        DB::raw('sum(total) as revenue')
                    )
                        ->whereDate('created_at', '>=', $dateFrom)
                        ->whereDate('created_at', '<=', $dateTo)
                        ->groupBy('date')
                        ->orderBy('date', 'asc')
                        ->get();
                }
            } else {
                // Sin filtro, mostrar últimos 7 días
                $last7Days = Order::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('count(*) as count'),
                    DB::raw('sum(total) as revenue')
                )
                    ->where('created_at', '>=', now()->subDays(7))
                    ->groupBy('date')
                    ->orderBy('date', 'desc')
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'payment_methods' => $paymentMethods,
                    'last_7_days' => $last7Days,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las estadísticas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener detalle de una orden específica.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $order = Order::with([
                'user:id,name,email,tel,cedula_type,cedula_ID',
                'address',
                'items.product:id,name,price,sku',
                'paymentProof'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Actualizar el estado de una orden.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending_payment,paid,shipped,completed,cancelled,refunded',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($id);

            // Guardar el estado anterior para logs o historial
            $previousStatus = $order->status;

            $order->status = $request->status;

            // Si hay notas adicionales, agregarlas
            if ($request->filled('notes')) {
                $order->notes = $order->notes
                    ? $order->notes . "\n\n[" . now()->format('Y-m-d H:i:s') . "] " . $request->notes
                    : "[" . now()->format('Y-m-d H:i:s') . "] " . $request->notes;
            }

            $order->save();

            // Crear mensaje del sistema cuando cambia el estado
            $statusMessages = [
                'pending_payment' => 'La orden está pendiente de pago',
                'paid' => 'La orden ha sido pagada y está siendo procesada',
                'shipped' => 'La orden ha sido enviada',
                'completed' => 'La orden ha sido completada',
                'cancelled' => 'La orden ha sido cancelada',
                'refunded' => 'La orden ha sido reembolsada',
            ];

            $systemMessage = Message::create([
                'order_id' => $order->id,
                'user_id' => null, // Mensaje del sistema no tiene usuario
                'message' => $statusMessages[$request->status] ?? "La orden ha cambiado de estado a: {$request->status}",
                'is_system' => true,
                'system_message_type' => 'status_change',
            ]);

            // Emitir evento de broadcasting para el mensaje del sistema
            try {
                // Para mensajes del sistema, pasamos null como sender
                // El evento manejará esto correctamente
                broadcast(new OrderMessageSent($systemMessage, null, $order));
            } catch (\Exception $e) {
                Log::error('Error al emitir evento de mensaje del sistema', [
                    'error' => $e->getMessage(),
                    'order_id' => $order->id,
                ]);
            }

            // Otorgar puntos cuando la orden se marca como 'paid'
            // 1$ = 0.03 puntos
            if ($request->status === 'paid' && $order->user) {
                // Solo otorgar puntos si no se han otorgado antes para esta orden
                if (!$order->user->pointTransactions()
                    ->where('order_id', $order->id)
                    ->where('type', 'earned')
                    ->exists()) {
                    $pointsEarned = $order->user->earnPoints((float) $order->total, $order->id, "Puntos ganados por orden pagada #{$order->id}");
                    // Guardar puntos ganados en la orden
                    $order->points_earned = $pointsEarned;
                    $order->save();
                }
            }

            DB::commit();

            // Recargar orden con relaciones
            $order->refresh();
            $order->load(['user:id,name', 'delivery:id,name', 'address']);

            // Crear notificaciones para cliente y delivery
            try {
                $notificationService = app(NotificationService::class);
                $orderNumber = 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT);
                
                // Títulos y mensajes según el estado
                $statusLabels = [
                    'pending_payment' => 'Pendiente de pago',
                    'paid' => 'Pagada',
                    'shipped' => 'Enviada',
                    'completed' => 'Completada',
                    'cancelled' => 'Cancelada',
                    'refunded' => 'Reembolsada',
                ];
                $statusLabel = $statusLabels[$request->status] ?? $request->status;

                // Notificar al cliente
                if ($order->user && in_array($request->status, ['paid', 'shipped', 'completed', 'cancelled', 'refunded'])) {
                    $titles = [
                        'paid' => 'Orden pagada',
                        'shipped' => 'Orden enviada',
                        'completed' => 'Orden completada',
                        'cancelled' => 'Orden cancelada',
                        'refunded' => 'Orden reembolsada',
                    ];
                    
                    $messages = [
                        'paid' => "Tu orden #{$orderNumber} ha sido pagada y está siendo procesada",
                        'shipped' => "Tu orden #{$orderNumber} ha sido enviada",
                        'completed' => "Tu orden #{$orderNumber} ha sido completada",
                        'cancelled' => "Tu orden #{$orderNumber} ha sido cancelada",
                        'refunded' => "Tu orden #{$orderNumber} ha sido reembolsada",
                    ];
                    
                    $title = $titles[$request->status] ?? 'Estado de orden actualizado';
                    $body = $messages[$request->status] ?? "Tu orden #{$orderNumber} ha cambiado de estado a: {$statusLabel}";
                    
                    $notificationService->create(
                        $order->user_id,
                        'order_status_changed',
                        $title,
                        $body,
                        [
                            'order_id' => $order->id,
                            'order_number' => $orderNumber,
                            'status' => $request->status,
                            'status_label' => $statusLabel,
                        ],
                        true // Enviar push notification
                    );
                }

                // Notificar al delivery si está asignado
                if ($order->delivery_id && in_array($request->status, ['paid', 'shipped', 'cancelled', 'refunded'])) {
                    $notificationService->create(
                        $order->delivery_id,
                        'order_status_changed',
                        'Estado de orden actualizado por admin',
                        "El admin cambió el estado de la orden #{$orderNumber} a: {$statusLabel}",
                        [
                            'order_id' => $order->id,
                            'order_number' => $orderNumber,
                            'status' => $request->status,
                            'status_label' => $statusLabel,
                            'user_id' => $order->user_id,
                            'user_name' => $order->user?->name,
                        ],
                        true // Enviar push notification
                    );
                }
            } catch (\Exception $e) {
                // No fallar la actualización del estado si la notificación falla
                Log::error('Error al crear notificaciones de cambio de estado', [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'status' => $request->status,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado correctamente',
                'data' => [
                    'order' => $order->load(['user:id,name,email', 'address', 'items', 'paymentProof']),
                    'previous_status' => $previousStatus,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar las notas internas de una orden.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateNotes(Request $request, $id)
    {
        $request->validate([
            'notes' => 'required|string|max:2000',
        ]);

        try {
            $order = Order::findOrFail($id);

            // Agregar timestamp a las notas
            $timestampedNote = "[" . now()->format('Y-m-d H:i:s') . "] " . $request->notes;

            $order->notes = $order->notes
                ? $order->notes . "\n\n" . $timestampedNote
                : $timestampedNote;

            $order->save();

            return response()->json([
                'success' => true,
                'message' => 'Notas actualizadas correctamente',
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar las notas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver el comprobante de pago de una orden.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewPaymentProof($id)
    {
        try {
            $order = Order::with('paymentProof')->findOrFail($id);

            if (!$order->paymentProof) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta orden no tiene comprobante de pago',
                ], 404);
            }

            $proof = $order->paymentProof;
            $filePath = $proof->file_path;
            $fullPath = storage_path('app/public/' . $filePath);

            // Verificar que el archivo existe físicamente
            $exists = file_exists($fullPath);
            
            if (!$exists) {
                Log::warning('Comprobante de pago no encontrado en el sistema de archivos', [
                    'order_id' => $order->id,
                    'file_path' => $filePath,
                    'full_path' => $fullPath,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'El archivo del comprobante de pago no se encuentra en el servidor',
                    'data' => [
                        'payment_proof' => $proof,
                        'file_path' => $filePath,
                        'exists' => false,
                    ],
                ], 404);
            }

            // Obtener información del archivo
            $fileSize = filesize($fullPath);
            $fileName = basename($filePath);
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            $mimeType = mime_content_type($fullPath);

            // Generar URL del comprobante
            $url = Storage::url($filePath);

            // Verificar si el archivo es accesible públicamente (symlink)
            $publicPath = public_path('storage/' . $filePath);
            $publiclyAccessible = file_exists($publicPath) || is_link(public_path('storage'));

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_proof' => $proof,
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                    'file_size' => $fileSize,
                    'file_size_human' => $this->formatBytes($fileSize),
                    'file_extension' => $fileExtension,
                    'mime_type' => $mimeType,
                    'url' => $url,
                    'exists' => true,
                    'publicly_accessible' => $publiclyAccessible,
                    'full_path' => $fullPath,
                    'public_path' => $publicPath,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener el comprobante de pago', [
                'order_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el comprobante de pago',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Formatear bytes a formato legible.
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Eliminar una orden (solo si está en estado pending_payment o cancelled).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $order = Order::findOrFail($id);

            // Solo permitir eliminar órdenes pendientes o canceladas
            if (!in_array($order->status, ['pending_payment', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden eliminar órdenes en estado pendiente o canceladas',
                ], 400);
            }

            // Eliminar comprobante de pago si existe
            if ($order->paymentProof && $order->paymentProof->file_path) {
                Storage::delete($order->paymentProof->file_path);
                $order->paymentProof->delete();
            }

            // Eliminar items de la orden
            $order->items()->delete();

            // Eliminar la orden
            $order->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Orden eliminada correctamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la orden',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener todas las órdenes de un usuario específico.
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserOrders($userId)
    {
        try {
            $orders = Order::with(['address', 'items.product:id,name', 'paymentProof'])
                ->where('user_id', $userId)
                ->recent()
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $orders,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las órdenes del usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
