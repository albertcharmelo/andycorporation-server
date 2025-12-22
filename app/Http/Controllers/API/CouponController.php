<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\UserCoupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /**
     * Obtener cupones disponibles del usuario autenticado.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myCoupons(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status', 'available'); // available, used, expired

        $query = $user->coupons()
            ->with('coupon')
            ->where('status', $status);

        $coupons = $query->orderBy('created_at', 'desc')->get();

        // Filtrar cupones válidos
        $validCoupons = $coupons->filter(function ($userCoupon) {
            return $userCoupon->coupon && $userCoupon->coupon->isValid();
        });

        return response()->json([
            'success' => true,
            'data' => $validCoupons->values(),
        ]);
    }

    /**
     * Validar y obtener información de un cupón por código.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'amount' => 'nullable|numeric|min:0', // Monto de compra para validar min_purchase_amount
        ]);

        $coupon = Coupon::where('code', $request->code)->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Cupón no encontrado',
            ], 404);
        }

        if (!$coupon->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'El cupón no es válido o ha expirado',
            ], 400);
        }

        // Validar monto mínimo de compra
        if ($request->amount && $coupon->min_purchase_amount) {
            if ($request->amount < $coupon->min_purchase_amount) {
                return response()->json([
                    'success' => false,
                    'message' => "El monto mínimo de compra es {$coupon->min_purchase_amount}",
                ], 400);
            }
        }

        $discount = 0;
        if ($request->amount) {
            $discount = $coupon->calculateDiscount($request->amount);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'coupon' => $coupon,
                'discount' => $discount,
            ],
        ]);
    }

    /**
     * Obtener un cupón específico del usuario.
     *
     * @param Request $request
     * @param int $userCouponId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $userCouponId)
    {
        $user = $request->user();

        $userCoupon = UserCoupon::where('user_id', $user->id)
            ->where('id', $userCouponId)
            ->with('coupon')
            ->first();

        if (!$userCoupon) {
            return response()->json([
                'success' => false,
                'message' => 'Cupón no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $userCoupon,
        ]);
    }
}
