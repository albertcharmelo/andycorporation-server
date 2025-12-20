<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DeliveryController extends Controller
{
    /**
     * Listar todos los delivery (usuarios con rol delivery).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $deliveries = User::role('delivery')
                ->select('id', 'name', 'email', 'tel', 'created_at')
                ->withCount(['assignedOrders as active_orders' => function ($query) {
                    $query->whereIn('status', ['paid', 'shipped']);
                }])
                ->withCount('assignedOrders as total_deliveries')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $deliveries,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener deliveries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear un nuevo usuario delivery.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'tel' => 'required|string|min:10|max:15|unique:users,tel',
            'cedula_type' => 'required|in:v,j,e,g,r,p',
            'cedula_ID' => 'required|string|min:7|max:20|unique:users,cedula_ID',
        ]);

        DB::beginTransaction();
        try {
            $delivery = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'tel' => $request->tel,
                'cedula_type' => $request->cedula_type,
                'cedula_ID' => $request->cedula_ID,
                'email_verified_at' => now(), // Auto-verificar
            ]);

            // Asignar rol delivery
            $delivery->assignRole('delivery');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Delivery creado exitosamente',
                'data' => $delivery,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear delivery',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Asignar delivery a una orden.
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignToOrder(Request $request, $orderId)
    {
        $request->validate([
            'delivery_id' => 'required|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($orderId);

            // Verificar que el usuario sea delivery
            $delivery = User::findOrFail($request->delivery_id);
            if (!$delivery->hasRole('delivery')) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario seleccionado no es un delivery',
                ], 400);
            }

            // Asignar delivery
            $order->assignDelivery($request->delivery_id);

            // Actualizar estado si está en pending_payment o paid
            if (in_array($order->status, ['pending_payment', 'paid'])) {
                $order->update(['status' => 'shipped']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Delivery asignado exitosamente',
                'data' => $order->load('delivery:id,name,email,tel'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar delivery',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Desasignar delivery de una orden.
     *
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function unassignFromOrder($orderId)
    {
        DB::beginTransaction();
        try {
            $order = Order::findOrFail($orderId);

            $order->update([
                'delivery_id' => null,
                'assigned_at' => null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Delivery desasignado exitosamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al desasignar delivery',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener órdenes asignadas a un delivery específico.
     *
     * @param int $deliveryId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrders($deliveryId)
    {
        try {
            $delivery = User::findOrFail($deliveryId);

            if (!$delivery->hasRole('delivery')) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no es un delivery',
                ], 400);
            }

            $orders = Order::with(['user:id,name,email,tel', 'address'])
                ->where('delivery_id', $deliveryId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $orders,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener órdenes del delivery',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un delivery (solo si no tiene órdenes activas).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $delivery = User::findOrFail($id);

            if (!$delivery->hasRole('delivery')) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no es un delivery',
                ], 400);
            }

            // Verificar si tiene órdenes activas
            $activeOrders = Order::where('delivery_id', $id)
                ->whereIn('status', ['paid', 'shipped'])
                ->count();

            if ($activeOrders > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar. El delivery tiene órdenes activas',
                ], 400);
            }

            // Desasignar de todas las órdenes
            Order::where('delivery_id', $id)->update([
                'delivery_id' => null,
                'assigned_at' => null,
            ]);

            // Eliminar usuario
            $delivery->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Delivery eliminado exitosamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar delivery',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Asignar rol delivery a un usuario existente.
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignRole($userId)
    {
        DB::beginTransaction();
        try {
            $user = User::findOrFail($userId);

            // Verificar si ya tiene el rol delivery
            if ($user->hasRole('delivery')) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario ya tiene el rol delivery',
                ], 400);
            }

            // Asignar rol delivery
            $user->assignRole('delivery');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Rol delivery asignado exitosamente',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'roles' => $user->getRoleNames()->toArray(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar rol delivery',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
