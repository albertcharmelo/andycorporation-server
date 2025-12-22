<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\User;
use App\Models\UserCoupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CouponController extends Controller
{
    /**
     * Listar todos los cupones.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $search = $request->query('search');
        $type = $request->query('type');
        $isActive = $request->query('is_active');

        $query = Coupon::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($isActive !== null) {
            $query->where('is_active', $isActive === 'true' || $isActive === '1');
        }

        $coupons = $query->withCount(['userCoupons', 'orders'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $coupons->items(),
            'pagination' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
            ],
        ]);
    }

    /**
     * Crear un nuevo cupón.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'promotional_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'type' => 'required|in:discount,free_delivery,gift,points_bonus',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'points_bonus' => 'nullable|integer|min:0',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_user' => 'required|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
        ]);

        // Validar que según el tipo, se proporcione el campo correspondiente
        if ($request->type === 'discount') {
            if (!$request->discount_amount && !$request->discount_percentage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe proporcionar discount_amount o discount_percentage para cupones de descuento',
                ], 422);
            }
        }

        if ($request->type === 'points_bonus' && !$request->points_bonus) {
            return response()->json([
                'success' => false,
                'message' => 'Debe proporcionar points_bonus para cupones de puntos bonus',
            ], 422);
        }

        $data = $request->except(['promotional_image']);

        // Manejar imagen promocional
        if ($request->hasFile('promotional_image')) {
            $imagePath = $request->file('promotional_image')->store('coupons', 'public');
            $data['promotional_image'] = $imagePath;
        }

        $coupon = Coupon::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Cupón creado exitosamente',
            'data' => $coupon,
        ], 201);
    }

    /**
     * Obtener un cupón específico.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $coupon = Coupon::withCount(['userCoupons', 'orders'])
            ->with(['userCoupons.user:id,name,email'])
            ->find($id);

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Cupón no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $coupon,
        ]);
    }

    /**
     * Actualizar un cupón.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);

        $request->validate([
            'code' => 'sometimes|string|max:50|unique:coupons,code,' . $id,
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'promotional_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'type' => 'sometimes|in:discount,free_delivery,gift,points_bonus',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'points_bonus' => 'nullable|integer|min:0',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_user' => 'sometimes|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
        ]);

        $data = $request->except(['promotional_image']);

        // Manejar imagen promocional
        if ($request->hasFile('promotional_image')) {
            // Eliminar imagen anterior si existe
            if ($coupon->promotional_image) {
                Storage::disk('public')->delete($coupon->promotional_image);
            }
            $imagePath = $request->file('promotional_image')->store('coupons', 'public');
            $data['promotional_image'] = $imagePath;
        }

        $coupon->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Cupón actualizado exitosamente',
            'data' => $coupon->fresh(),
        ]);
    }

    /**
     * Eliminar un cupón.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $coupon = Coupon::findOrFail($id);

        // Verificar si hay cupones usados
        $usedCount = $coupon->userCoupons()->where('status', 'used')->count();
        if ($usedCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un cupón que ya ha sido utilizado',
            ], 400);
        }

        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cupón eliminado exitosamente',
        ]);
    }

    /**
     * Asignar cupón a usuarios.
     *
     * @param Request $request
     * @param int $couponId
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignToUsers(Request $request, $couponId)
    {
        $coupon = Coupon::findOrFail($couponId);

        $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $assigned = [];
            $skipped = [];

            foreach ($request->user_ids as $userId) {
                // Verificar si el usuario ya tiene este cupón
                $existing = UserCoupon::where('user_id', $userId)
                    ->where('coupon_id', $couponId)
                    ->first();

                if ($existing) {
                    $skipped[] = $userId;
                    continue;
                }

                // Verificar máximo de usos por usuario
                $userUses = UserCoupon::where('user_id', $userId)
                    ->where('coupon_id', $couponId)
                    ->where('status', 'used')
                    ->count();

                if ($userUses >= $coupon->max_uses_per_user) {
                    $skipped[] = $userId;
                    continue;
                }

                UserCoupon::create([
                    'user_id' => $userId,
                    'coupon_id' => $couponId,
                    'status' => 'available',
                ]);

                $assigned[] = $userId;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cupones asignados exitosamente',
                'data' => [
                    'assigned' => $assigned,
                    'skipped' => $skipped,
                    'total_assigned' => count($assigned),
                    'total_skipped' => count($skipped),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar cupones: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de un cupón.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics($id)
    {
        $coupon = Coupon::findOrFail($id);

        $stats = [
            'total_assigned' => $coupon->userCoupons()->count(),
            'total_used' => $coupon->userCoupons()->where('status', 'used')->count(),
            'total_available' => $coupon->userCoupons()->where('status', 'available')->count(),
            'total_expired' => $coupon->userCoupons()->where('status', 'expired')->count(),
            'total_orders' => $coupon->orders()->count(),
            'total_discount_given' => $coupon->orders()->sum('discount_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
