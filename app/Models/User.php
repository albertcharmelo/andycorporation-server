<?php
namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles, SoftDeletes;

    protected $guard_name = 'api';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tel',
        'cedula_type',
        'cedula_ID',
        'google_id',
        'avatar',
        'points',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'points'            => 'decimal:2',
        ];
    }

    /**
     * Obtener los tems del carrito de un usuario.
     *
     * @return array<string, string>
     */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function addresses()
    {
        return $this->hasMany(UserAddress::class);
    }

    /**
     * Un usuario puede tener muchas órdenes.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Un delivery puede tener muchas órdenes asignadas.
     */
    public function assignedOrders()
    {
        return $this->hasMany(Order::class, 'delivery_id');
    }

    /**
     * Un usuario puede enviar muchos mensajes.
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }


    /**
     * Transacciones de puntos del usuario.
     */
    public function pointTransactions()
    {
        return $this->hasMany(PointTransaction::class);
    }

    /**
     * Obtener puntos disponibles del usuario.
     */
    public function getAvailablePoints(): float
    {
        return (float) ($this->points ?? 0);
    }

    /**
     * Verificar si el usuario puede usar una cantidad de puntos.
     * Mínimo 100 puntos para usar.
     */
    public function canUsePoints(int $points): bool
    {
        $availablePoints = $this->getAvailablePoints();
        
        // Mínimo 100 puntos para usar
        if ($points < 100) {
            return false;
        }
        
        return $availablePoints >= $points;
    }

    /**
     * Usar puntos del usuario.
     * Retorna true si se usaron correctamente, false si no hay suficientes.
     */
    public function usePoints(int $points, ?int $orderId = null, ?string $description = null): bool
    {
        if (!$this->canUsePoints($points)) {
            return false;
        }

        $this->points = max(0, $this->getAvailablePoints() - $points);
        $this->save();

        // Registrar transacción
        PointTransaction::create([
            'user_id' => $this->id,
            'order_id' => $orderId,
            'type' => 'used',
            'points' => $points,
            'description' => $description ?? "Puntos usados en orden #{$orderId}",
            'balance_after' => $this->points,
        ]);

        return true;
    }

    /**
     * Ganar puntos automáticamente.
     * 1$ = 0.03 puntos
     * @return float Puntos ganados
     */
    public function earnPoints(float $amount, ?int $orderId = null, ?string $description = null): float
    {
        // Calcular puntos: 1$ = 0.03 puntos
        $pointsEarned = round($amount * 0.03, 2);

        if ($pointsEarned <= 0) {
            return 0;
        }

        $this->points = $this->getAvailablePoints() + $pointsEarned;
        $this->save();

        // Registrar transacción
        PointTransaction::create([
            'user_id' => $this->id,
            'order_id' => $orderId,
            'type' => 'earned',
            'points' => $pointsEarned,
            'description' => $description ?? "Puntos ganados por orden #{$orderId}",
            'balance_after' => $this->points,
        ]);

        return $pointsEarned;
    }

    /**
     * Calcular descuento en dólares basado en puntos.
     * 100 puntos = 1$ de descuento
     */
    public function calculatePointsDiscount(int $points): float
    {
        // 100 puntos = 1$ de descuento
        return round($points / 100, 2);
    }

    /**
     * Asignar puntos ya calculados al usuario.
     * Útil cuando los puntos se calcularon previamente (ej: órdenes pre-registradas).
     */
    public function assignPoints(float $points, ?int $orderId = null, ?string $description = null): void
    {
        if ($points <= 0) {
            return;
        }

        $this->points = $this->getAvailablePoints() + $points;
        $this->save();

        // Registrar transacción
        \App\Models\PointTransaction::create([
            'user_id' => $this->id,
            'order_id' => $orderId,
            'type' => 'earned',
            'points' => $points,
            'description' => $description ?? "Puntos asignados por orden #{$orderId}",
            'balance_after' => $this->points,
        ]);
    }

    /**
     * Asignar órdenes pre-registradas al usuario cuando se registra.
     * Busca órdenes POS pre-registradas que coincidan por teléfono o cédula.
     */
    public function assignPreRegisteredOrders(): array
    {
        $assignedOrders = [];
        
        // Buscar órdenes pre-registradas que coincidan por teléfono o cédula
        $preRegisteredOrders = Order::where('is_pre_registered', true)
            ->whereNull('user_id')
            ->where(function ($query) {
                if ($this->tel) {
                    $query->where('customer_tel', $this->tel);
                }
                if ($this->cedula_ID) {
                    $query->orWhere('customer_cedula_ID', $this->cedula_ID);
                }
            })
            ->get();

        foreach ($preRegisteredOrders as $order) {
            // Asignar la orden al usuario
            $order->update([
                'user_id' => $this->id,
                'is_pre_registered' => false,
            ]);

            // Transferir puntos ganados al usuario
            if ($order->points_earned > 0) {
                $this->assignPoints(
                    (float) $order->points_earned,
                    $order->id,
                    "Puntos ganados por orden POS pre-registrada #{$order->id}"
                );
            }

            $assignedOrders[] = $order;
        }

        return $assignedOrders;
    }
}
