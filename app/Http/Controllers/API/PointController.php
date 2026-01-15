<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PointTransaction;
use Illuminate\Http\Request;

class PointController extends Controller
{
    /**
     * Obtener puntos disponibles del usuario autenticado.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'points' => $user->getAvailablePoints(),
                'points_formatted' => number_format($user->getAvailablePoints(), 2),
                'discount_available' => $user->calculatePointsDiscount((int) $user->getAvailablePoints()),
                'can_use_points' => $user->getAvailablePoints() >= 100,
                'minimum_points' => 100,
            ],
        ]);
    }

    /**
     * Obtener historial de transacciones de puntos.
     */
    public function transactions(Request $request)
    {
        $user = $request->user();
        $perPage = $request->query('per_page', 20);

        $transactions = PointTransaction::where('user_id', $user->id)
            ->with('order:id,status,total')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Validar puntos antes de checkout.
     */
    public function validatePoints(Request $request)
    {
        $request->validate([
            'points' => 'required|integer|min:100',
            'order_total' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $points = $request->input('points');
        $orderTotal = $request->input('order_total');

        // Validar que el usuario tenga suficientes puntos
        if (!$user->canUsePoints($points)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes suficientes puntos disponibles',
                'available_points' => $user->getAvailablePoints(),
            ], 400);
        }

        // Calcular descuento
        $discount = $user->calculatePointsDiscount($points);

        // El descuento no puede exceder el total de la orden
        if ($discount > $orderTotal) {
            $maxDiscount = $orderTotal;
            $maxPoints = (int) ($orderTotal * 100); // 100 puntos = 1$
            
            return response()->json([
                'success' => false,
                'message' => "El descuento no puede exceder el total de la orden. Máximo: {$maxPoints} puntos (${$maxDiscount})",
                'max_points' => $maxPoints,
                'max_discount' => $maxDiscount,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'points' => $points,
                'discount' => $discount,
                'discount_formatted' => number_format($discount, 2),
                'new_total' => $orderTotal - $discount,
                'new_total_formatted' => number_format($orderTotal - $discount, 2),
            ],
        ]);
    }
}
