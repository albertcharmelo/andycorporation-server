<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'promotional_image',
        'type',
        'discount_amount',
        'discount_percentage',
        'points_bonus',
        'min_purchase_amount',
        'max_uses',
        'max_uses_per_user',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'min_purchase_amount' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Cupones asignados a usuarios.
     */
    public function userCoupons()
    {
        return $this->hasMany(UserCoupon::class);
    }

    /**
     * Órdenes que usaron este cupón.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Verificar si el cupón es válido.
     */
    public function isValid()
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $now->gt($this->valid_until)) {
            return false;
        }

        // Verificar máximo de usos
        if ($this->max_uses !== null) {
            $usedCount = $this->userCoupons()->where('status', 'used')->count();
            if ($usedCount >= $this->max_uses) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calcular el descuento para un monto dado.
     */
    public function calculateDiscount($amount)
    {
        if (!$this->isValid()) {
            return 0;
        }

        if ($this->type === 'discount') {
            if ($this->discount_percentage) {
                return $amount * ($this->discount_percentage / 100);
            }
            if ($this->discount_amount) {
                return min($this->discount_amount, $amount);
            }
        }

        return 0;
    }
}
