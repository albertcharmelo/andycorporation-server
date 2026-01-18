<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Listar todos los clientes con paginación, búsqueda y estadísticas.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 20);
            $search = $request->get('search');

            // Obtener usuarios con rol 'client' o sin roles (clientes por defecto)
            $query = User::where(function ($q) {
                $q->role('client')
                  ->orWhereDoesntHave('roles');
            });

            // Aplicar búsqueda
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('tel', 'like', "%{$search}%")
                      ->orWhere('cedula_ID', 'like', "%{$search}%");
                });
            }

            // Obtener usuarios con sus estadísticas de órdenes
            $users = $query->select('id', 'name', 'email', 'tel', 'cedula_type', 'cedula_ID', 'points', 'created_at')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Agregar estadísticas de órdenes para cada usuario
            $users->getCollection()->transform(function ($user) {
                $orders = Order::where('user_id', $user->id)->get();
                
                $ordersByStatus = $orders->groupBy('status')->map->count();
                
                $user->orders_count = $orders->count();
                $user->orders_by_status = [
                    'pending_payment' => $ordersByStatus->get('pending_payment', 0),
                    'paid' => $ordersByStatus->get('paid', 0),
                    'shipped' => $ordersByStatus->get('shipped', 0),
                    'completed' => $ordersByStatus->get('completed', 0),
                    'cancelled' => $ordersByStatus->get('cancelled', 0),
                    'refunded' => $ordersByStatus->get('refunded', 0),
                ];
                $user->total_spent = $orders->whereIn('status', ['paid', 'shipped', 'completed'])->sum('total');
                
                return $user;
            });

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los clientes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener detalles completos de un cliente.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $user = User::with(['addresses', 'pointTransactions' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(50);
            }])->findOrFail($id);

            // Obtener órdenes del cliente
            $orders = Order::with(['items.product:id,name', 'address', 'paymentProof'])
                ->where('user_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();

            // Calcular estadísticas de órdenes
            $ordersByStatus = $orders->groupBy('status')->map->count();
            
            $ordersStatistics = [
                'total' => $orders->count(),
                'pending_payment' => $ordersByStatus->get('pending_payment', 0),
                'paid' => $ordersByStatus->get('paid', 0),
                'shipped' => $ordersByStatus->get('shipped', 0),
                'completed' => $ordersByStatus->get('completed', 0),
                'cancelled' => $ordersByStatus->get('cancelled', 0),
                'refunded' => $ordersByStatus->get('refunded', 0),
                'total_spent' => $orders->whereIn('status', ['paid', 'shipped', 'completed'])->sum('total'),
            ];

            // Calcular estadísticas de puntos
            $pointTransactions = $user->pointTransactions;
            $pointsStatistics = [
                'total_earned' => (float) $pointTransactions->where('type', 'earned')->sum('points'),
                'total_used' => (float) $pointTransactions->where('type', 'used')->sum('points'),
                'current_balance' => $user->getAvailablePoints(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tel' => $user->tel,
                    'cedula_type' => $user->cedula_type,
                    'cedula_ID' => $user->cedula_ID,
                    'points' => $user->getAvailablePoints(),
                    'points_statistics' => $pointsStatistics,
                    'orders' => $orders,
                    'orders_statistics' => $ordersStatistics,
                    'addresses' => $user->addresses,
                    'created_at' => $user->created_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado',
                'error' => $e->getMessage(),
            ], 404);
        }
    }
}
