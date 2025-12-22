<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCoupon extends Model
{
    protected $fillable = [
        'user_id',
        'coupon_id',
        'status',
        'used_in_order_id',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    /**
     * Usuario que tiene el cupón.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cupón asociado.
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Orden donde se usó el cupón.
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'used_in_order_id');
    }

    /**
     * Marcar cupón como utilizado.
     */
    public function markAsUsed($orderId)
    {
        $this->update([
            'status' => 'used',
            'used_in_order_id' => $orderId,
            'used_at' => now(),
        ]);
    }

    /**
     * Verificar si el cupón está disponible.
     */
    public function isAvailable()
    {
        return $this->status === 'available' && $this->coupon->isValid();
    }
}
