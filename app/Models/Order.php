<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'address_id',
        'subtotal',
        'shipping_cost',
        'total',
        'payment_method',
        'payment_reference',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal'      => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total'         => 'decimal:2',
    ];

    /**
     * Una orden pertenece a un usuario.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Una orden tiene muchos ítems de orden.
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Una orden tiene una dirección de envío (opcional).
     */
    public function address()
    {
        return $this->belongsTo(UserAddress::class, 'address_id');
    }
}