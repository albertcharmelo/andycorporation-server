<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
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

        $orders = Order::with(['user:id,name,email,tel', 'address', 'items', 'paymentProof'])
            ->byStatus($status)
            ->byPaymentMethod($paymentMethod)
            ->search($search)
            ->recent()
            ->paginate($perPage);

        return Inertia::render('admin/Orders', [
            'orders' => $orders,
            'filters' => [
                'status' => $status,
                'payment_method' => $paymentMethod,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Mostrar detalle de una orden (Inertia).
     *
     * @param int $id
     * @return \Inertia\Response
     */
    public function show($id)
    {
        $order = Order::with([
            'user:id,name,email,tel,cedula_type,cedula_ID',
            'address',
            'items.product:id,name,price,sku',
            'paymentProof'
        ])->findOrFail($id);

        return Inertia::render('admin/OrderDetail', [
            'order' => $order,
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
            'status' => 'required|in:pending_payment,paid,shipped,completed,cancelled,refunded',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($id);

            $order->status = $request->status;

            // Si hay notas adicionales, agregarlas
            if ($request->filled('notes')) {
                $order->notes = $order->notes
                    ? $order->notes . "\n\n[" . now()->format('Y-m-d H:i:s') . "] " . $request->notes
                    : "[" . now()->format('Y-m-d H:i:s') . "] " . $request->notes;
            }

            $order->save();

            DB::commit();

            return redirect()->back()->with('success', 'Estado actualizado correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error al actualizar el estado: ' . $e->getMessage());
        }
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
