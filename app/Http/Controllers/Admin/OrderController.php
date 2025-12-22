<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OrderController extends Controller
{
    /**
     * Mostrar lista de órdenes (Inertia).
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $status = $request->get('status', 'all');
        $paymentMethod = $request->get('payment_method');
        $search = $request->get('search');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $orders = Order::with(['user:id,name,email,tel', 'address', 'items', 'paymentProof'])
            ->byStatus($status)
            ->byPaymentMethod($paymentMethod)
            ->search($search)
            ->byDateRange($dateFrom, $dateTo)
            ->recent()
            ->paginate($perPage);

        return Inertia::render('admin/Orders', [
            'orders' => $orders,
            'filters' => [
                'status' => $status,
                'payment_method' => $paymentMethod,
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }


    /**
     * Actualizar el estado de una orden.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending_payment,paid,received,invoiced,in_agency,on_the_way,shipped,delivered,completed,cancelled,refunded',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($id);
            $previousStatus = $order->status;

            // Actualizar estado
            $order->status = $request->status;

            // Si hay notas adicionales, agregarlas
            if ($request->filled('notes')) {
                $order->notes = $order->notes
                    ? $order->notes . "\n\n[" . now()->format('Y-m-d H:i:s') . "] " . $request->notes
                    : "[" . now()->format('Y-m-d H:i:s') . "] " . $request->notes;
            }

            $order->save();

            // Registrar en el historial solo si cambió el estado
            if ($previousStatus !== $request->status) {
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => $request->status,
                    'status_label' => $request->status,
                    'changed_by_user_id' => auth()->id(),
                    'comment' => $request->notes,
                ]);
            }

            DB::commit();

            return redirect()->back()->with('success', 'Estado actualizado correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error al actualizar el estado: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar detalle de una orden (Inertia) - Inicializar historial si no existe.
     */
    public function show($id)
    {
        // Cargar orden con relaciones optimizadas
        $order = Order::with([
            'user:id,name,email,tel,cedula_type,cedula_ID',
            'address',
            'items.product:id,name,price,sku',
            'paymentProof:id,order_id,file_path,created_at',
            'delivery:id,name,email,tel',
            'statusHistory' => function ($query) {
                $query->with('changedBy:id,name,email')
                      ->orderBy('created_at', 'asc');
            },
            // Limitar mensajes iniciales a los últimos 50 para mejorar rendimiento
            'messages' => function ($query) {
                $query->with('user:id,name,email,avatar')
                      ->latest()
                      ->limit(50);
            }
        ])->findOrFail($id);

        // Si no hay historial, crear entrada inicial (solo una vez)
        if ($order->statusHistory->isEmpty()) {
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $order->status,
                'status_label' => $order->status,
                'changed_by_user_id' => $order->user_id,
                'comment' => 'Orden creada',
            ]);
            // Recargar relación solo si fue necesario crear
            $order->load(['statusHistory.changedBy:id,name,email']);
        }

        // Obtener estadísticas del chat de forma optimizada (una sola query)
        $messagesStats = Message::where('order_id', $order->id)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread, MAX(created_at) as last_message_at')
            ->first();

        $chatStats = [
            'total_messages' => (int) ($messagesStats->total ?? 0),
            'unread_messages' => (int) ($messagesStats->unread ?? 0),
            'last_message_at' => $messagesStats->last_message_at,
        ];

        // Invertir orden de mensajes para mostrar más recientes al final
        if ($order->messages) {
            $order->messages = $order->messages->reverse()->values();
        }

        return Inertia::render('admin/OrderDetail', [
            'order' => $order,
            'chatStats' => $chatStats,
        ]);
    }

    /**
     * Actualizar las notas internas de una orden.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
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

            return redirect()->back()->with('success', 'Notas actualizadas correctamente');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al actualizar las notas: ' . $e->getMessage());
        }
    }
}
