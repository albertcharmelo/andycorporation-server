<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PointsConfig;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    /**
     * Buscar cliente por teléfono, cédula/RIF, nombre o email.
     * Solo busca usuarios con rol 'client' (excluye delivery y admin).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchCustomer(Request $request)
    {
        $request->validate([
            'search' => 'required|string|min:3',
        ]);

        $search = $request->search;
        $searchLower = strtolower($search);

        // Buscar solo usuarios con rol 'client' o sin roles (clientes por defecto)
        // Excluir usuarios con roles 'delivery' o 'admin'
        $customers = User::where(function ($q) {
                $q->role('client')
                  ->orWhereDoesntHave('roles');
            })
            ->where(function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(tel) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(cedula_ID) LIKE ?', ["%{$searchLower}%"]);
            })
            ->select('id', 'name', 'email', 'tel', 'cedula_type', 'cedula_ID')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * Obtener información completa de un cliente por ID.
     *
     * @param int $customerId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCustomer($customerId)
    {
        $customer = User::with('addresses')
            ->find($customerId);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        // Obtener el score actual del cliente (suma de puntos de todas sus órdenes)
        $customerScore = Order::where('user_id', $customerId)
            ->sum('customer_score');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'tel' => $customer->tel,
                'cedula_type' => $customer->cedula_type,
                'cedula_ID' => $customer->cedula_ID,
                'addresses' => $customer->addresses,
                'score' => $customerScore,
            ],
        ]);
    }

    /**
     * Listar productos para el POS (con stock disponible).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProducts(Request $request)
    {
        $search = $request->query('search', '');
        $perPage = $request->query('per_page', 50);

        $query = Product::with('images');

        if ($search) {
            $searchLower = strtolower($search);
            $query->where(function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$searchLower}%"]);
            });
        }

        $products = $query->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Crear una venta POS (orden desde tienda).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createSale(Request $request)
    {
        $seller = $request->user();

        // Validar que el vendedor sea admin (por ahora)
        if (!$seller->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para realizar ventas POS',
            ], 403);
        }

        $request->validate([
            // Datos del cliente
            'customer_id' => 'nullable|exists:users,id', // Si es cliente registrado
            'customer_name' => 'required_without:customer_id|string|max:255',
            'customer_lastname' => 'required_without:customer_id|string|max:255',
            'customer_tel' => 'required|string|min:10|max:15',
            'customer_cedula_type' => 'nullable|in:v,j,e,g,r,p',
            'customer_cedula_ID' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string|max:500',
            
            // Productos de la orden
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.comment' => 'nullable|string|max:500',
            
            // Información de pago
            'currency' => 'required|in:BS,USD',
            'amount_bs' => 'nullable|numeric|min:0',
            'amount_usd' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:manual_transfer,mobile_payment,credit_card,paypal,binance',
            'payment_reference' => 'nullable|string|max:255',
            
            // Otros
            'notes' => 'nullable|string|max:1000',
            'customer_score' => 'nullable|integer|min:0',
        ]);

        // Validar que al menos un monto esté presente según la moneda
        if ($request->currency === 'BS' && !$request->amount_bs) {
            return response()->json([
                'success' => false,
                'message' => 'El monto en bolívares es requerido cuando la moneda es BS',
            ], 422);
        }

        if ($request->currency === 'USD' && !$request->amount_usd) {
            return response()->json([
                'success' => false,
                'message' => 'El monto en dólares es requerido cuando la moneda es USD',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Obtener o buscar cliente
            $customer = null;
            if ($request->customer_id) {
                // Cliente seleccionado explícitamente
                $customer = User::find($request->customer_id);
            } else {
                // Si no se seleccionó cliente, buscar por teléfono o cédula
                // Validación de seguridad: si el tel/cédula ya existe, asignar automáticamente
                if ($request->customer_tel || $request->customer_cedula_ID) {
                    $existingCustomer = User::where(function ($query) use ($request) {
                        if ($request->customer_tel) {
                            $query->where('tel', $request->customer_tel);
                        }
                        if ($request->customer_cedula_ID) {
                            $query->orWhere('cedula_ID', $request->customer_cedula_ID);
                        }
                    })
                    ->where(function ($query) {
                        // Solo buscar usuarios con rol client o sin roles (excluir delivery y admin)
                        $query->role('client')
                              ->orWhereDoesntHave('roles');
                    })
                    ->first();
                    
                    if ($existingCustomer) {
                        // Cliente existe: asignar automáticamente
                        $customer = $existingCustomer;
                    }
                    // Si no existe, $customer sigue siendo null y se creará orden pre-registrada
                }
            }

            // Calcular subtotal y total
            $subtotal = 0;
            $orderItems = [];

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                
                // Validar stock
                if ($product->stock_quantity < $item['quantity']) {
                    throw new \Exception("Stock insuficiente para el producto: {$product->name}");
                }

                $itemSubtotal = $item['quantity'] * $item['unit_price'];
                $subtotal += $itemSubtotal;

                $orderItems[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $itemSubtotal,
                    'comment' => $item['comment'] ?? null,
                ];
            }

            $shippingCost = 0; // En ventas POS no hay envío
            $total = $subtotal + $shippingCost;

            // Determinar el monto según la moneda
            $amountBs = $request->amount_bs ?? 0;
            $amountUsd = $request->amount_usd ?? 0;

            // Obtener score del cliente (si es cliente registrado, usar su score acumulado)
            $customerScore = $request->customer_score ?? 0;
            if ($customer) {
                $customerScore = Order::where('user_id', $customer->id)->sum('customer_score');
            }

            // Calcular puntos ganados
            $pointsEarned = 0;
            $isPreRegistered = false;
            
            if ($customer) {
                // Usuario registrado: calcular y asignar puntos inmediatamente
                $pointsConfig = PointsConfig::getActive();
                if ($pointsConfig) {
                    // Determinar la moneda para calcular puntos
                    $currencyForPoints = $request->currency;
                    $amountForPoints = $currencyForPoints === 'USD' ? $amountUsd : $amountBs;
                    
                    // Convertir BS a USD si es necesario (asumiendo tasa fija, ajustar según necesidad)
                    if ($currencyForPoints === 'BS' && $pointsConfig->currency === 'USD') {
                        // Aquí se podría agregar conversión de moneda si es necesario
                        // Por ahora, asumimos que la configuración está en la misma moneda
                    }
                    
                    $pointsEarned = $pointsConfig->calculatePoints($amountForPoints, $currencyForPoints);
                    
                    // Asignar puntos inmediatamente al usuario
                    if ($pointsEarned > 0) {
                        $customer->earnPoints((float) $pointsEarned, null, "Puntos ganados por orden POS (pendiente)");
                    }
                }
            } else {
                // Usuario no registrado: calcular puntos pero no asignarlos aún
                $pointsConfig = PointsConfig::getActive();
                if ($pointsConfig) {
                    $currencyForPoints = $request->currency;
                    $amountForPoints = $currencyForPoints === 'USD' ? $amountUsd : $amountBs;
                    $pointsEarned = $pointsConfig->calculatePoints($amountForPoints, $currencyForPoints);
                }
                $isPreRegistered = true;
            }

            // Crear la orden
            $order = Order::create([
                'user_id' => $customer ? $customer->id : null,
                'seller_id' => $seller->id,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'payment_method' => $request->payment_method,
                'payment_reference' => $request->payment_reference,
                'status' => 'paid', // Las ventas POS se marcan como pagadas inmediatamente
                'notes' => $request->notes,
                // Campos POS
                'customer_name' => $request->customer_name ?? $customer->name ?? null,
                'customer_lastname' => $request->customer_lastname ?? null,
                'customer_tel' => $request->customer_tel ?? $customer->tel ?? null,
                'customer_cedula_type' => $request->customer_cedula_type ?? $customer->cedula_type ?? null,
                'customer_cedula_ID' => $request->customer_cedula_ID ?? $customer->cedula_ID ?? null,
                'customer_address' => $request->customer_address ?? null,
                'currency' => $request->currency,
                'amount_bs' => $amountBs,
                'amount_usd' => $amountUsd,
                'customer_score' => $customerScore,
                'is_pos_order' => true,
                'is_pre_registered' => $isPreRegistered,
                'points_earned' => $pointsEarned,
            ]);
            
            // Si el usuario está registrado y se asignaron puntos, actualizar la transacción con el order_id
            if ($customer && $pointsEarned > 0) {
                $latestTransaction = $customer->pointTransactions()
                    ->where('type', 'earned')
                    ->whereNull('order_id')
                    ->latest()
                    ->first();
                
                if ($latestTransaction) {
                    $latestTransaction->update([
                        'order_id' => $order->id,
                        'description' => "Puntos ganados por orden POS #{$order->id}"
                    ]);
                }
            }

            // Crear los items de la orden
            foreach ($orderItems as $itemData) {
                $product = $itemData['product'];
                
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price_at_purchase' => $itemData['unit_price'],
                    'quantity' => $itemData['quantity'],
                ]);

                // Actualizar stock del producto
                $product->decrement('stock_quantity', $itemData['quantity']);
                
                // Si el stock llega a 0, actualizar status
                if ($product->fresh()->stock_quantity <= 0) {
                    $product->update(['stock_status' => 'outofstock']);
                }
            }

            DB::commit();

            // Cargar relaciones para la respuesta
            $order->load(['items.product', 'seller:id,name,email', 'user:id,name,email']);

            return response()->json([
                'success' => true,
                'message' => 'Venta POS creada exitosamente',
                'data' => $order,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la venta POS: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener historial de ventas POS.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSalesHistory(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $sellerId = $request->query('seller_id');

        $query = Order::where('is_pos_order', true)
            ->with(['seller:id,name,email', 'user:id,name,email', 'items.product'])
            ->orderBy('created_at', 'desc');

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        $sales = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $sales->items(),
            'pagination' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ],
        ]);
    }
}
