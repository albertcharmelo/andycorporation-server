<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentProof;


use App\Models\UserAddress; // Para validar la dirección de envío
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    /**
     * 1. Crear Orden: Procesa la información del carrito y la dirección de envío
     * para crear una nueva orden de compra.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createOrder(Request $request)
    {
        // Validar los datos de la solicitud
        $request->validate([
            'address_id'        => 'required|exists:user_addresses,id',  // Debe ser una dirección existente
            'payment_method'    => 'required|string|in:manual_transfer', // Método de pago esperado
            'payment_reference' => 'required|string|max:255|unique:orders,payment_reference', // Referencia de pago obligatoria (transferencia bancaria del usuario)
            'notes'             => 'nullable|string|max:1000', // Notas adicionales opcionales
            'payment_proof'    => 'required|file|image|max:2048', // 2048 KB = 2MB
        ]);

        $user = $request->user();

        // 1. Obtener los ítems del carrito del usuario
        $cartItems = $user->cartItems()->with('product')->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'El carrito de compras está vacío.'], 400);
        }

        // Calcular subtotal
        $subtotal = 0;
        foreach ($cartItems as $item) {
            $subtotal += $item->quantity * $item->price_at_purchase;
        }

                              // Puedes añadir lógica para calcular shipping_cost aquí si es necesario
        $shippingCost = 0.00; // Por ahora, costo de envío fijo
        $total        = $subtotal + $shippingCost;

        // Validar que la dirección de envío pertenezca al usuario
        $address = $user->addresses()->find($request->address_id);
        if (! $address) {
            return response()->json(['message' => 'La dirección de envío seleccionada no es válida o no te pertenece.'], 400);
        }

        try {
            DB::beginTransaction();

            // 2. Crear una nueva instancia de la orden
            // payment_reference viene del frontend (referencia de transferencia bancaria del usuario)
            $order = new Order([
                'user_id'           => $user->id,
                'address_id'        => $request->address_id,
                'subtotal'          => $subtotal,
                'shipping_cost'     => $shippingCost,
                'total'             => $total,
                'payment_method'    => $request->payment_method,
                'payment_reference' => $request->payment_reference, // Referencia de pago proporcionada por el usuario
                'status'            => 'pending_payment',
                'notes'             => $request->notes,
            ]);

            // 3. Guardar la orden en la base de datos para obtener el UUID
            $order->save();

            // 4. Mover ítems del carrito a order_items usando el ID de la orden recién creada
            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id'          => $order->id, // Ahora el ID de la orden ya está garantizado
                    'product_id'        => $item->product_id,
                    'product_name'      => $item->product->name,
                    'price_at_purchase' => $item->price_at_purchase,
                    'quantity'          => $item->quantity,
                ]);
            }

            // 5. Subir y guardar el comprobante de pago
            if ($request->hasFile('payment_proof')) {
                // Guarda la imagen en el disco público y obtiene la ruta
                // La imagen se guardará en storage/app/public/payment_proofs
                $filePath = $request->file('payment_proof')->store('payment_proofs', 'public');

                // Registra la información del comprobante en la nueva tabla
                PaymentProof::create([
                    'order_id'  => $order->id,
                    'file_path' => $filePath,
                    'notes'     => $request->notes,
                ]);
            }

            // 5. Vaciar el carrito después de crear la orden
            $user->cartItems()->delete();

            DB::commit();
                                                                                  // 4. Mostrar resumen (La respuesta de esta API es el resumen inicial)
                                                                                  // Se puede extender para cargar más detalles si es necesario para el resumen
            $order = Order::with(['items.product', 'address'])->find($order->id); // Recarga la orden con relaciones

            return response()->json([
                'message'              => 'Orden creada exitosamente. Pago pendiente.',
                'order'                => $order->toArray(), // Convierte el modelo a array para JSON
                'payment_instructions' => [
                    'method'    => 'Transferencia Bancaria',
                    'reference' => $order->payment_reference,
                    // Aquí podrías añadir los detalles de la cuenta bancaria para la transferencia
                    // 'bank_name'       => 'Banco Ejemplo',
                    // 'account_number'  => '1234-5678-9012-3456',
                    // 'account_holder'  => 'Nombre de la Empresa S.A.',
                    // 'amount_to_pay'   => $order->total,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear la orden: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Módulo de Confirmación: Muestra el resumen de una orden específica.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $orderId El ID de la orden a mostrar.
     * @return \Illuminate\Http\JsonResponse
     */
    public function showOrderSummary(Request $request, $orderId)
    {
        $user = $request->user();

        // Buscar la orden por ID y asegurarse de que pertenece al usuario
        $order = $user->orders()->with(['items.product', 'address'])->find($orderId);

        if (! $order) {
            return response()->json(['message' => 'Orden no encontrada o no te pertenece.'], 404);
        }

        return response()->json([
            'message' => 'Resumen de la orden.',
            'order'   => $order->toArray(),
        ]);
    }

    // Aquí podrías añadir un método para actualizar el estado de la orden a 'paid'
    // una vez que el usuario confirme la transferencia, o un webhook de pasarela de pago.
    // Por ejemplo: public function confirmPayment(Request $request, $orderId) {...}

    /**
     * NUEVA API: Permite al usuario confirmar que ha realizado un pago manual.
     * Esto actualiza el estado de la orden a 'paid' para su revisión.
     */
    public function confirmPayment(Request $request, $orderId)
    {
        $user = $request->user();

        // 1. Buscar la orden y verificar que pertenezca al usuario
        $order = $user->orders()->find($orderId);

        if (! $order) {
            return response()->json(['message' => 'Orden no encontrada o no te pertenece.'], 404);
        }

        // 2. Verificar que el estado actual sea 'pending_payment'
        if ($order->status !== 'pending_payment') {
            return response()->json(['message' => 'El pago de esta orden ya ha sido procesado o no está pendiente.'], 400);
        }

        try {
            DB::beginTransaction();

            // 3. Actualizar el estado de la orden a 'paid'
            $order->status = 'paid';
            $order->save();

            DB::commit();

            return response()->json([
                'message' => 'Pago confirmado exitosamente. La orden está siendo procesada.',
                'order'   => $order->toArray(),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al confirmar el pago: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener las órdenes del usuario autenticado.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserOrders(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado',
            ], 401);
        }

        // Obtener parámetros opcionales para filtros
        $status = $request->query('status'); // Filtrar por estado
        $perPage = $request->query('per_page', 15); // Paginación

        // Consulta base con relaciones
        $query = $user->orders()
            ->with(['items.product', 'address'])
            ->orderBy('created_at', 'desc');

        // Aplicar filtro de estado si se proporciona
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        // Paginar resultados
        $orders = $query->paginate($perPage);

        return response()->json([
            'message' => 'Órdenes obtenidas exitosamente.',
            'orders'  => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    /**
     * Obtener el detalle de una orden específica del usuario.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserOrderDetail(Request $request, $orderId)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado',
            ], 401);
        }

        // Buscar la orden por ID y asegurarse de que pertenece al usuario
        $order = $user->orders()
            ->with(['items.product', 'address', 'paymentProof'])
            ->find($orderId);

        if (! $order) {
            return response()->json(['message' => 'Orden no encontrada o no te pertenece.'], 404);
        }

        return response()->json([
            'message' => 'Detalle de la orden.',
            'order'   => $order->toArray(),
        ]);
    }

}
