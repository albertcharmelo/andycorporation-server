<?php

namespace App\Http\Controllers\API\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Mapeo de estados a labels legibles en español.
     */
    private function getStatusLabel($status)
    {
        $labels = [
            'pending_payment' => 'Pago Pendiente',
            'paid' => 'Pagado',
            'received' => 'Recibido',
            'invoiced' => 'Facturado',
            'in_agency' => 'En Agencia',
            'shipped' => 'Enviado',
            'on_the_way' => 'En camino',
            'delivered' => 'Entregado',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
            'refunded' => 'Reembolsado',
        ];

        return $labels[$status] ?? $status;
    }

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
            'status' => $this->getStatusLabel($order->status),
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
                ->where('delivery_id', $deliveryId)
                ->orderBy('created_at', 'desc');

            // Filtro por estado
            if ($request->has('status') && $request->status !== 'all' && $request->status !== '') {
                $query->where('status', $request->status);
            }

            // Filtro por SOS (cuando se implemente el campo sos_status)
            // Por ahora este filtro no tendrá efecto hasta que se agregue el campo a la base de datos
            if ($request->boolean('sos_only')) {
                // TODO: Agregar filtro cuando exista el campo sos_status en la tabla orders
                // $query->where('sos_status', true);
            }

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
}

