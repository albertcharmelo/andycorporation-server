<?php

namespace App\Http\Controllers\API\Delivery;

use App\Http\Controllers\Controller;
use App\Events\DeliveryLocationUpdated;
use App\Events\OrderSOSActivated;
use App\Models\DeliveryLocation;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\ExpoPushNotificationService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Formatear dirección completa.
     */
    private function formatAddress($address)
    {
        if (!$address) {
            return null;
        }

        $parts = [];
        if ($address->address_line_1) {
            $parts[] = $address->address_line_1;
        }
        if ($address->address_line_2) {
            $parts[] = $address->address_line_2;
        }
        if ($address->name) {
            $parts[] = $address->name;
        }

        return implode(', ', $parts) ?: null;
    }

    /**
     * Formatear número de orden como PED-001, PED-0123, etc.
     */
    private function formatOrderNumber($id)
    {
        return 'PED-' . str_pad($id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Formatear orden al formato simplificado requerido.
     */
    private function formatOrder($order)
    {
        $address = $order->address;
        
        return [
            'id' => $order->id,
            'numero' => $this->formatOrderNumber($order->id),
            'usuario' => $order->user->name ?? 'N/A',
            'status' => $order->status,
            'direccion' => $this->formatAddress($address),
            'latitud' => $address ? $address->latitude : null,
            'longitud' => $address ? $address->longitude : null,
            'productos' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nombre' => $item->product_name ?? ($item->product->name ?? 'Producto eliminado'),
                    'cantidad' => $item->quantity,
                    'precio' => (float) $item->price_at_purchase,
                ];
            })->toArray(),
        ];
    }

    /**
     * Listar pedidos asignados al delivery autenticado.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $deliveryId = auth()->id();

            // Construir query base
            $query = Order::with(['user:id,name', 'address', 'items.product:id,name'])
                ->where('delivery_id', $deliveryId);

            // Filtro por período de fecha (basado en assigned_at)
            $datePeriod = $request->get('date_period', 'today');
            
            if ($datePeriod !== 'all') {
                $now = now();
                
                switch ($datePeriod) {
                    case 'today':
                        $query->whereNotNull('assigned_at')
                              ->whereDate('assigned_at', $now->toDateString());
                        break;
                    case 'yesterday':
                        $yesterday = $now->copy()->subDay();
                        $query->whereNotNull('assigned_at')
                              ->whereDate('assigned_at', $yesterday->toDateString());
                        break;
                    case 'week':
                        $startOfWeek = $now->copy()->startOfWeek();
                        $query->whereNotNull('assigned_at')
                              ->where('assigned_at', '>=', $startOfWeek);
                        break;
                }
            } else {
                // Para "Todos", incluir solo órdenes con assigned_at (asignadas)
                $query->whereNotNull('assigned_at');
            }

            // Filtro por estado - Solo estados relevantes para delivery
            // Las órdenes ya están filtradas por delivery_id, así que todas son asignadas a este delivery
            
            if ($request->has('status') && $request->status !== 'all' && $request->status !== '') {
                $status = $request->status;
                
                if ($status === 'in_agency') {
                    // "En Agencia" = todas las órdenes asignadas que NO están "en camino" ni "completadas"
                    // Cualquier orden asignada que no esté en ruta o completada está en agencia
                    $query->whereNotIn('status', ['on_the_way', 'delivered', 'completed']);
                } elseif ($status === 'on_the_way') {
                    // "En camino" = órdenes con estado on_the_way
                    $query->where('status', 'on_the_way');
                } elseif ($status === 'delivered') {
                    // "Entregado" = órdenes completadas o entregadas
                    $query->whereIn('status', ['delivered', 'completed']);
                }
            } else {
                // Si no se especifica estado, mostrar todas las órdenes asignadas que no están completadas/canceladas
                // Esto incluye: en agencia, en camino, y entregadas
                $query->whereNotIn('status', ['cancelled', 'refunded']);
            }

            // Filtro por SOS
            if ($request->boolean('sos_only')) {
                $query->where('sos_status', true);
            }

            // Ordenar por fecha de asignación descendente
            $query->orderBy('assigned_at', 'desc');

            // Obtener órdenes
            $orders = $query->get();

            // Formatear respuesta
            $formattedOrders = $orders->map(function ($order) {
                return $this->formatOrder($order);
            })->toArray();

            return response()->json($formattedOrders);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las órdenes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar estado del pedido (in_agency → on_the_way → delivered).
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $orderId)
    {
        $request->validate([
            'status' => 'required|in:on_the_way,delivered',
            'comment' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($orderId);
            $deliveryId = auth()->id();

            // Verificar que la orden esté asignada al delivery autenticado
            if ($order->delivery_id !== $deliveryId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para actualizar esta orden',
                ], 403);
            }

            $newStatus = $request->status;
            $currentStatus = $order->status;

            // Validar transiciones válidas
            $validTransitions = [
                'in_agency' => ['on_the_way'],
                'on_the_way' => ['delivered'],
            ];

            if (!isset($validTransitions[$currentStatus]) || !in_array($newStatus, $validTransitions[$currentStatus])) {
                return response()->json([
                    'success' => false,
                    'message' => "No se puede cambiar el estado de '{$currentStatus}' a '{$newStatus}'. Transiciones válidas: " . implode(' → ', $validTransitions[$currentStatus] ?? []),
                ], 400);
            }

            // Actualizar estado
            $updateData = ['status' => $newStatus];

            // Si cambia a on_the_way, guardar ubicación inicial si se proporciona
            if ($newStatus === 'on_the_way' && $request->has(['latitude', 'longitude'])) {
                DeliveryLocation::create([
                    'order_id' => $order->id,
                    'delivery_user_id' => $deliveryId,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                ]);

                $updateData['current_latitude'] = $request->latitude;
                $updateData['current_longitude'] = $request->longitude;
                $updateData['location_updated_at'] = now();
            }

            // Si cambia a delivered, actualizar delivered_at
            // Nota: Los puntos se otorgan cuando el status cambia a 'paid', no cuando se entrega
            if ($newStatus === 'delivered') {
                $updateData['delivered_at'] = now();
            }

            // Agregar comentario a notas si se proporciona
            if ($request->filled('comment')) {
                $timestampedNote = "[" . now()->format('Y-m-d H:i:s') . "] Delivery: " . $request->comment;
                $updateData['notes'] = $order->notes
                    ? $order->notes . "\n\n" . $timestampedNote
                    : $timestampedNote;
            }

            $order->update($updateData);

            DB::commit();

            // Recargar orden con relaciones
            $order->refresh();
            $order->load(['user:id,name', 'address', 'items', 'delivery:id,name']);

            // Crear notificaciones para cliente y admin
            try {
                $notificationService = app(NotificationService::class);
                $orderNumber = 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT);
                $statusLabels = [
                    'on_the_way' => 'En camino',
                    'delivered' => 'Entregada',
                ];
                $statusLabel = $statusLabels[$newStatus] ?? $newStatus;

                // Notificar al cliente
                if ($order->user) {
                    $notificationService->create(
                        $order->user_id,
                        'order_status_changed',
                        "Estado de orden actualizado",
                        "Tu orden #{$orderNumber} está ahora: {$statusLabel}",
                        [
                            'order_id' => $order->id,
                            'order_number' => $orderNumber,
                            'status' => $newStatus,
                            'status_label' => $statusLabel,
                            'delivery_name' => $order->delivery?->name,
                        ],
                        true // Enviar push notification
                    );
                }

                // Notificar a todos los admins
                $admins = User::role(['admin', 'super_admin'])->get();
                foreach ($admins as $admin) {
                    $notificationService->create(
                        $admin->id,
                        'order_status_changed',
                        "Estado de orden actualizado",
                        "El delivery {$order->delivery?->name} actualizó la orden #{$orderNumber} a: {$statusLabel}",
                        [
                            'order_id' => $order->id,
                            'order_number' => $orderNumber,
                            'status' => $newStatus,
                            'status_label' => $statusLabel,
                            'delivery_id' => $order->delivery_id,
                            'delivery_name' => $order->delivery?->name,
                            'user_id' => $order->user_id,
                            'user_name' => $order->user?->name,
                        ],
                        true // Enviar push notification
                    );
                }
            } catch (\Exception $e) {
                Log::error('Error al crear notificaciones de cambio de estado', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado exitosamente',
                'order' => $order,
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
     * Actualizar ubicación en tiempo real (solo si está en camino).
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateLocation(Request $request, $orderId)
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($orderId);
            $deliveryId = auth()->id();

            // Verificar que la orden esté asignada al delivery autenticado
            if ($order->delivery_id !== $deliveryId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para actualizar esta orden',
                ], 403);
            }

            // Validar que el estado permita actualización de ubicación
            if (!in_array($order->status, ['shipped', 'on_the_way'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se puede actualizar la ubicación cuando el pedido está en camino',
                    'current_status' => $order->status,
                ], 400);
            }

            // Guardar en historial de ubicaciones
            $location = DeliveryLocation::create([
                'order_id' => $order->id,
                'delivery_user_id' => $deliveryId,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ]);

            // Actualizar posición actual en la orden
            $order->update([
                'current_latitude' => $request->latitude,
                'current_longitude' => $request->longitude,
                'location_updated_at' => now(),
            ]);

            // Recargar orden con datos actualizados
            $order->refresh();

            DB::commit();

            // Emitir evento de actualización de ubicación vía Pusher
            event(new DeliveryLocationUpdated($location, $order));

            return response()->json([
                'message' => 'Ubicación actualizada exitosamente',
                'location' => [
                    'id' => $location->id,
                    'order_id' => $location->order_id,
                    'delivery_user_id' => $location->delivery_user_id,
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                    'created_at' => $location->created_at->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la ubicación',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activar SOS para un pedido.
     * Registra el SOS en las notas de la orden y actualiza el estado si es necesario.
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function sos(Request $request, $orderId)
    {
        $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($orderId);
            $deliveryId = auth()->id();

            // Verificar que la orden esté asignada al delivery autenticado
            if ($order->delivery_id !== $deliveryId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para activar SOS en esta orden',
                ], 403);
            }

            // Preparar datos de actualización
            $updateData = [
                'sos_status' => true,
                'sos_comment' => $request->comment,
                'sos_reported_at' => now(),
            ];

            // Agregar el SOS a las notas de la orden con timestamp
            $sosNote = "[" . now()->format('Y-m-d H:i:s') . "] 🚨 SOS ACTIVADO - Delivery: " . $request->comment;
            $updateData['notes'] = $order->notes
                ? $order->notes . "\n\n" . $sosNote
                : $sosNote;

            // Actualizar la orden
            $order->update($updateData);

            // Registrar en el historial de estados
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $order->status, // Mantener el estado actual
                'status_label' => $order->status . ' - SOS ACTIVADO',
                'changed_by_user_id' => $deliveryId,
                'comment' => 'SOS: ' . $request->comment,
            ]);

            DB::commit();

            // Recargar la orden con relaciones
            $order->refresh();
            $order->load(['user:id,name', 'address', 'delivery:id,name']);

            // 1. Emitir evento Pusher para el dashboard de admin
            try {
                broadcast(new OrderSOSActivated($order));
                Log::info('Evento SOS emitido exitosamente', [
                    'order_id' => $order->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Error al emitir evento SOS', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // 2. Crear notificación en DB para admin
            try {
                $notificationService = app(NotificationService::class);
                $orderNumber = 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT);
                
                // Notificar a todos los admins
                $admins = User::role(['admin', 'super_admin'])->get();
                foreach ($admins as $admin) {
                    $notificationService->create(
                        $admin->id,
                        'sos_activated',
                        '🚨 SOS Activado',
                        "El delivery {$order->delivery?->name} activó un SOS para la orden #{$orderNumber}",
                        [
                            'order_id' => $order->id,
                            'order_number' => $orderNumber,
                            'sos_comment' => $order->sos_comment,
                            'sos_reported_at' => $order->sos_reported_at?->toIso8601String(),
                            'delivery_id' => $order->delivery_id,
                            'delivery_name' => $order->delivery?->name,
                            'user_id' => $order->user_id,
                            'user_name' => $order->user?->name,
                        ],
                        true // Enviar push notification
                    );
                }
            } catch (\Exception $e) {
                Log::error('Error al crear notificaciones DB de SOS', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // 3. Enviar notificaciones push a órdenes del día no entregadas
            try {
                $this->notifyTodayOrders($order);
            } catch (\Exception $e) {
                Log::error('Error al enviar notificaciones push SOS', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'message' => 'SOS activado exitosamente. El administrador ha sido notificado.',
                'order' => [
                    'id' => $order->id,
                    'sos_status' => $order->sos_status,
                    'sos_comment' => $order->sos_comment,
                    'sos_reported_at' => $order->sos_reported_at->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al activar SOS',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enviar notificaciones push a todas las órdenes del día que no han sido entregadas.
     * Se excluye la orden que activó el SOS.
     *
     * @param Order $triggerOrder La orden que activó el SOS
     * @return void
     */
    private function notifyTodayOrders(Order $triggerOrder): void
    {
        try {
            // Obtener órdenes del día que no han sido entregadas
            $todayOrders = Order::whereDate('created_at', today())
                ->whereNotIn('status', ['delivered', 'completed'])
                ->where('id', '!=', $triggerOrder->id) // Excluir la orden que activó el SOS
                ->with(['user.pushTokens' => function ($query) {
                    $query->where('is_active', true);
                }])
                ->get();

            if ($todayOrders->isEmpty()) {
                Log::info('No hay órdenes del día para notificar sobre SOS', [
                    'trigger_order_id' => $triggerOrder->id,
                ]);
                return;
            }

            $notificationService = new ExpoPushNotificationService();
            $notifiedCount = 0;

            foreach ($todayOrders as $order) {
                if (!$order->user) {
                    continue;
                }

                // Obtener tokens activos del usuario
                $tokens = $order->user->pushTokens()->where('is_active', true)->get();

                if ($tokens->isEmpty()) {
                    continue;
                }

                // Enviar notificación a cada token del usuario
                $orderNumber = 'ORD-' . str_pad($order->id, 6, '0', STR_PAD_LEFT);
                $title = 'Alerta SOS Activada';
                $body = "Se ha activado un SOS en otra orden del día. Revisa tu pedido #{$orderNumber}.";

                $sent = $notificationService->sendToUser(
                    $order->user_id,
                    $title,
                    $body,
                    [
                        'type' => 'sos_alert',
                        'order_id' => $order->id,
                        'order_number' => $orderNumber,
                        'trigger_order_id' => $triggerOrder->id,
                        'trigger_order_number' => 'ORD-' . str_pad($triggerOrder->id, 6, '0', STR_PAD_LEFT),
                        'sos_comment' => $triggerOrder->sos_comment,
                        'sos_reported_at' => $triggerOrder->sos_reported_at?->toIso8601String(),
                    ]
                );

                if ($sent > 0) {
                    $notifiedCount++;
                    Log::info('Notificación SOS enviada a usuario', [
                        'user_id' => $order->user_id,
                        'order_id' => $order->id,
                        'tokens_sent' => $sent,
                    ]);
                }
            }

            Log::info('Notificaciones SOS enviadas a órdenes del día', [
                'trigger_order_id' => $triggerOrder->id,
                'total_orders' => $todayOrders->count(),
                'notified_users' => $notifiedCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al enviar notificaciones push SOS a órdenes del día', [
                'trigger_order_id' => $triggerOrder->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

