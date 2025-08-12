<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentProof extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'file_path',
        'notes',
    ];

    /**
     * Get the order that owns the payment proof.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
