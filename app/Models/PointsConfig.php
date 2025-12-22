<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointsConfig extends Model
{
    protected $table = 'points_config';

    protected $fillable = [
        'points_per_currency',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'points_per_currency' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Obtener la configuración activa de puntos.
     */
    public static function getActive()
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Calcular puntos basados en el monto de compra.
     */
    public function calculatePoints($amount, $currency = 'USD')
    {
        if (!$this->is_active) {
            return 0;
        }

        // Si la moneda es diferente, convertir (por ahora asumimos que es la misma)
        // En el futuro se puede agregar conversión de moneda
        return floor($amount * $this->points_per_currency);
    }
}
