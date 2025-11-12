<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }

        $isAdmin = $user->hasAnyRole(['admin', 'super_admin']);

        $dashboardData = [
            'isAdmin' => $isAdmin,
        ];

        if ($isAdmin) {
            // Estadísticas para admin
            $dashboardData['stats'] = [
                'total_orders' => Order::count(),
                'pending_orders' => Order::where('status', 'pending_payment')->count(),
                'total_revenue' => Order::whereIn('status', ['paid', 'shipped', 'completed'])->sum('total'),
                'total_users' => User::count(),
                'total_products' => Product::count(),
            ];

            // Órdenes recientes (últimas 5)
            $dashboardData['recent_orders'] = Order::with(['user:id,name,email'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            // Estadísticas de la última semana
            $dashboardData['weekly_stats'] = Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as orders_count'),
                DB::raw('sum(total) as revenue')
            )
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get()
                ->map(function ($stat) {
                    return [
                        'date' => $stat->date,
                        'orders_count' => (int) $stat->orders_count,
                        'revenue' => (string) $stat->revenue,
                    ];
                });

            // Productos más vendidos (últimos 30 días)
            $dashboardData['top_products'] = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.created_at', '>=', now()->subDays(30))
                ->whereIn('orders.status', ['paid', 'shipped', 'completed'])
                ->select(
                    'order_items.product_name',
                    DB::raw('SUM(order_items.quantity) as total_sold'),
                    DB::raw('SUM(order_items.quantity * order_items.price_at_purchase) as total_revenue')
                )
                ->groupBy('order_items.product_name')
                ->orderBy('total_sold', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($product) {
                    return [
                        'product_name' => $product->product_name,
                        'total_sold' => (int) $product->total_sold,
                        'total_revenue' => (string) $product->total_revenue,
                    ];
                });
        } else {
            // Estadísticas para cliente
            $dashboardData['stats'] = [
                'total_orders' => Order::where('user_id', $user->id)->count(),
                'pending_orders' => Order::where('user_id', $user->id)
                    ->where('status', 'pending_payment')
                    ->count(),
                'completed_orders' => Order::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->count(),
                'total_spent' => Order::where('user_id', $user->id)
                    ->whereIn('status', ['paid', 'shipped', 'completed'])
                    ->sum('total'),
            ];

            // Órdenes recientes del usuario
            $dashboardData['recent_orders'] = Order::where('user_id', $user->id)
                ->with(['items', 'address'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $dashboardData,
        ]);
    }
}
