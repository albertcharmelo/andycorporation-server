<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'message',
        'message_type',
        'file_path',
        'is_delivery_message',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_delivery_message' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Un mensaje pertenece a una orden.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Un mensaje pertenece a un usuario (quien lo envió).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para obtener mensajes no leídos.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope para obtener mensajes de una orden específica.
     */
    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId)->orderBy('created_at', 'asc');
    }

    /**
     * Scope para obtener mensajes después de una fecha específica (útil para delivery).
     */
    public function scopeAfterDate($query, $date)
    {
        return $query->where('created_at', '>=', $date);
    }

    /**
     * Scope para filtrar mensajes del delivery.
     */
    public function scopeDeliveryMessages($query)
    {
        return $query->where('is_delivery_message', true);
    }

    /**
     * Scope para filtrar mensajes previos al delivery.
     */
    public function scopePreDeliveryMessages($query)
    {
        return $query->where('is_delivery_message', false);
    }

    /**
     * Marcar mensaje como leído.
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
