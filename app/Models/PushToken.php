<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'expo_push_token',
        'device_id',
        'platform',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para obtener solo tokens activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Validar formato de token Expo
     * Los tokens de Expo tienen el formato: ExponentPushToken[xxxxx]
     */
    public static function isValidExpoToken(string $token): bool
    {
        return preg_match('/^ExponentPushToken\[.+\]$/', $token) === 1;
    }
}
