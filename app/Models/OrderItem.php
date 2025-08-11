<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    // Se eliminan las propiedades y el método para UUIDs para usar un ID numérico
    // protected $keyType      = 'string';
    // public $incrementing    = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'price_at_purchase',
        'quantity',
    ];

    protected $casts = [
        'price_at_purchase' => 'decimal:2',
        'quantity'          => 'integer',
    ];

    // Se elimina el método boot() para la generación de UUIDs

    /**
     * Un ítem de orden pertenece a una orden.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Un ítem de orden pertenece a un producto.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
