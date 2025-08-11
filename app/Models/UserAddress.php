<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'address_line_1',
        'address_line_2',
        'referencia',
        'postal_code',
        'is_default',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'latitude' => 'decimal:8',   // Castea a decimal con 8 decimales
        'longitude' => 'decimal:8',  // Castea a decimal con 8 decimales
    ];

    /**
     * Una dirección pertenece a un usuario.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}