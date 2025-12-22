<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'seller_id',
        'delivery_id',
        'address_id',
        'subtotal',
        'shipping_cost',
        'total',
        'payment_method',
        'payment_reference',
        'status',
        'notes',
        'assigned_at',
        'delivered_at',
        'sos_status',
        'sos_comment',
        'sos_reported_at',
        'current_latitude',
        'current_longitude',
        'location_updated_at',
        // Campos POS
        'customer_name',
        'customer_lastname',
        'customer_tel',
        'customer_cedula_type',
        'customer_cedula_ID',
        'customer_address',
        'currency',
        'amount_bs',
        'amount_usd',
        'customer_score',
        'is_pos_order',
        // Campos de cupones
        'coupon_id',
        'discount_amount',
    ];

    protected $casts = [
        'subtotal'           => 'decimal:2',
        'shipping_cost'      => 'decimal:2',
        'total'              => 'decimal:2',
        'assigned_at'        => 'datetime',
        'delivered_at'       => 'datetime',
        'sos_status'         => 'boolean',
        'sos_reported_at'    => 'datetime',
        'location_updated_at' => 'datetime',
        'current_latitude'   => 'decimal:8',
        'current_longitude'  => 'decimal:8',
        'amount_bs'          => 'decimal:2',
        'amount_usd'          => 'decimal:2',
        'customer_score'     => 'integer',
        'is_pos_order'       => 'boolean',
        'discount_amount'    => 'decimal:2',
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

    /**
     * Una orden puede tener un comprobante de pago.
     */
    public function paymentProof()
    {
        return $this->hasOne(PaymentProof::class);
    }

    /**
     * Scope para filtrar órdenes por estado.
     */
    public function scopeByStatus($query, $status)
    {
        if ($status && $status !== 'all') {
            return $query->where('status', $status);
        }
        return $query;
    }

    /**
     * Scope para filtrar órdenes por método de pago.
     */
    public function scopeByPaymentMethod($query, $paymentMethod)
    {
        if ($paymentMethod) {
            return $query->where('payment_method', $paymentMethod);
        }
        return $query;
    }

    /**
     * Scope para buscar órdenes por referencia de pago o usuario.
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where(function ($q) use ($search) {
                $q->where('payment_reference', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }
        return $query;
    }

    /**
     * Scope para filtrar órdenes por rango de fechas.
     */
    public function scopeByDateRange($query, $dateFrom, $dateTo)
    {
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        return $query;
    }

    /**
     * Scope para órdenes recientes (las más nuevas primero).
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Una orden puede tener un delivery asignado.
     */
    public function delivery()
    {
        return $this->belongsTo(User::class, 'delivery_id');
    }

    /**
     * Una orden puede tener un vendedor asignado (POS).
     */
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Una orden puede tener un cupón aplicado.
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Una orden tiene muchas ubicaciones de delivery (historial de tracking).
     */
    public function deliveryLocations()
    {
        return $this->hasMany(DeliveryLocation::class)->orderBy('created_at', 'desc');
    }

    /**
     * Una orden tiene un historial de cambios de estado (timeline).
     */
    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at', 'asc');
    }

    /**
     * Una orden tiene muchos mensajes (chat).
     */
    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    /**
     * Obtener mensajes no leídos de la orden.
     */
    public function unreadMessages()
    {
        return $this->hasMany(Message::class)->where('is_read', false);
    }

    /**
     * Asignar delivery a la orden.
     */
    public function assignDelivery($deliveryId)
    {
        $this->update([
            'delivery_id' => $deliveryId,
            'assigned_at' => now(),
        ]);
    }

    /**
     * Marcar orden como entregada.
     */
    public function markAsDelivered()
    {
        $this->update([
            'status' => 'completed',
            'delivered_at' => now(),
        ]);
    }
}