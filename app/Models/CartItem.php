<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'price_at_purchase',
    ];

    /**
     * Un CartItem pertenece a un usuario.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Un CartItem pertenece a un producto.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
