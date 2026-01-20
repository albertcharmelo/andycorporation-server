<?php

use App\Http\Controllers\API\AddressController;
use App\Http\Controllers\API\Admin\ChatController;
use App\Http\Controllers\API\Admin\DeliveryController;
use App\Http\Controllers\API\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\API\Admin\PosController;
use App\Http\Controllers\API\Admin\PointsConfigController;
use App\Http\Controllers\API\PointController;
use App\Http\Controllers\API\PushTokenController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\CheckoutController;
use App\Http\Controllers\API\Delivery\OrderController as DeliveryOrderController;
use App\Http\Controllers\API\OrderChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('syncProducts', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'syncProducts']);


Route::get('/user', function (Request $request) {
    $user = $request->user();
    
    if (!$user) {
        return response()->json([
            'message' => 'No autenticado',
        ], 401);
    }
    
    // Refrescar el usuario desde la base de datos para obtener los puntos actualizados
    $user->refresh();
    
    return [
        'user' => [
            ...$user->toArray(),
            'roles' => $user->getRoleNames()->toArray(),
            'is_admin' => $user->hasAnyRole(['admin', 'super_admin']),
            'is_delivery' => $user->hasRole('delivery'),
            'is_client' => $user->hasRole('client') || $user->getRoleNames()->isEmpty(),
            'points' => $user->getAvailablePoints(),
            'points_formatted' => number_format($user->getAvailablePoints(), 2),
            'can_use_points' => $user->getAvailablePoints() >= 100,
            'points_discount_available' => $user->calculatePointsDiscount((int) $user->getAvailablePoints()),
        ],
        'message' => 'Welcome to the API',
    ];
})->middleware('auth:sanctum');

// Ruta personalizada de autenticación de broadcasting para Pusher con Sanctum
// Usa nuestro controlador personalizado que valida el usuario
Route::post('/broadcasting/auth', [\App\Http\Controllers\BroadcastingAuthController::class, 'authenticate'])
    ->middleware(['auth:sanctum'])
    ->name('api.broadcasting.auth');

// Dashboard
Route::get('/dashboard', [\App\Http\Controllers\API\DashboardController::class, 'index'])
    ->middleware('auth:sanctum');

## Auth
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});


## Products
Route::prefix('products')->group(function () {
    Route::get('/', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'index']);
    Route::get('/promo', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'getPromotionalProducts']);
    Route::get('/listproducts', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'getAllProducts']);
    Route::get('/searchByName', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'searchProducts']);
    Route::get('/popularProducts', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'getPopularProducts']);
    Route::get('/salesProducts', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'getSalesProducts']);
    Route::get('/latestProducts', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'getLatestProducts']);
    Route::get('/{product}', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'getProduct']);
    ## Sincronizar con Wordpress
    Route::get('syncProducts', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'syncProducts']);
});

## Categories
Route::prefix('categories')->group(function () {
    Route::get('/', [\App\Http\Controllers\API\PRODUCTS\CategoriesController::class, 'index']);
});

Route::group(['middleware' => ['auth:sanctum']], function () {

    /*******************************/
    /*       carts           */
    /*******************************/
    Route::prefix('cart')->group(function () {                         // Rutas para el Módulo del Carrito
        Route::post('/add', [CartController::class, 'addProduct']);        // Añadir producto
        Route::post('/update', [CartController::class, 'updateQuantity']); // Actualizar cantidad
        Route::post('/remove', [CartController::class, 'removeProduct']);  // Eliminar producto
        Route::get('/', [CartController::class, 'showCart']);              // Mostrar carrito
                                                                           // Route::put('/update/{productId}', [CartController::class, 'updateQuantity']);   // Actualizar cantidad
                                                                           // Route::delete('/remove/{productId}', [CartController::class, 'removeProduct']); // Eliminar producto
    });

    /*******************************/
    /* Direcciones y Envío         */
    /*******************************/
    Route::prefix('addresses')->group(function () {
        Route::post('/add', [AddressController::class, 'store']);                          // Guardar nueva dirección
        Route::get('/', [AddressController::class, 'index']);                              // Mostrar todas las direcciones guardadas
        Route::get('/{addressId}', [AddressController::class, 'show']);                    // Mostrar una dirección específica
        Route::put('/{addressId}', [AddressController::class, 'update']);                  // Actualizar una dirección
        Route::delete('/{addressId}', [AddressController::class, 'destroy']);              // Eliminar una dirección
        Route::post('/{addressId}/set-default', [AddressController::class, 'setDefault']); // Establecer como predeterminada
    });

    // **********************************
    // * Rutas del Módulo de Checkout y Pago (NUEVAS) *
    // **********************************
    Route::prefix('checkout')->group(function () {
        // Crear Orden y Generar Referencia de Pago
        Route::post('/create-order', [CheckoutController::class, 'createOrder']);
        // Módulo de Confirmación: Mostrar resumen de una orden
        Route::get('/order-summary/{orderId}', [CheckoutController::class, 'showOrderSummary']);
        // Ruta para confirmar el pago.
        Route::post('/confirm-payment/{orderId}', [CheckoutController::class, 'confirmPayment']);
    });

    // Ruta para calcular costo de envío (puede ir fuera del prefix 'addresses' si es general)
    Route::post('/shipping-cost', [AddressController::class, 'calculateShippingCost']);

    // **********************************
    // * Rutas de Órdenes del Usuario *
    // **********************************
    Route::prefix('orders')->group(function () {
        Route::get('/', [CheckoutController::class, 'getUserOrders']);                              // Listar órdenes del usuario
        Route::get('/{orderId}', [CheckoutController::class, 'getUserOrderDetail']);                 // Detalle de una orden
        Route::get('/{orderId}/delivery-location', [CheckoutController::class, 'getDeliveryLocation']); // Obtener ubicación GPS del delivery
        Route::get('/{orderId}/chats', [OrderChatController::class, 'getMessages']);                  // Obtener mensajes
        Route::post('/{orderId}/chats', [OrderChatController::class, 'sendMessage']);                  // Enviar mensaje
        Route::put('/{orderId}/chats/mark-read', [OrderChatController::class, 'markAsRead']);        // Marcar como leído
        Route::get('/{orderId}/chats/stats', [OrderChatController::class, 'getStats']);               // Estadísticas del chat
        Route::get('/{orderId}/chats/attachment/{messageId}', [OrderChatController::class, 'getAttachment']); // Descargar archivo adjunto
    });

    // Puntos del Usuario
    Route::prefix('points')->group(function () {
        Route::get('/', [PointController::class, 'index']);                                            // Obtener puntos disponibles
        Route::get('/transactions', [PointController::class, 'transactions']);                        // Historial de transacciones
        Route::post('/validate', [PointController::class, 'validatePoints']);                          // Validar puntos antes de checkout
    });

    // Push Tokens (Notificaciones)
    Route::prefix('push-tokens')->group(function () {
        Route::post('/register', [PushTokenController::class, 'register']);                          // Registrar/actualizar token push
        Route::delete('/{token}', [PushTokenController::class, 'delete']);                             // Eliminar token push
    });

    // Notificaciones
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);                                    // Listar notificaciones con paginación
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);                    // Contador de no leídas
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);                       // Marcar como leída
        Route::put('/read-all', [NotificationController::class, 'markAllAsRead']);                      // Marcar todas como leídas
        Route::get('/{id}', [NotificationController::class, 'show']);                                  // Detalle de notificación
    });
});

// **********************************
// * Rutas de Administración (Admin Panel) *
// **********************************
Route::group(['middleware' => ['auth:sanctum', 'role:admin,super_admin'], 'prefix' => 'admin'], function () {

    // Gestión de Órdenes
    Route::prefix('orders')->group(function () {
        Route::get('/', [AdminOrderController::class, 'index']);                           // Listar todas las órdenes con filtros
        Route::get('/statistics', [AdminOrderController::class, 'statistics']);            // Estadísticas de órdenes
        Route::get('/{id}', [AdminOrderController::class, 'show']);                        // Ver detalle de orden
        Route::put('/{id}/status', [AdminOrderController::class, 'updateStatus']);         // Actualizar estado
        Route::put('/{id}/notes', [AdminOrderController::class, 'updateNotes']);           // Actualizar notas internas
        Route::get('/{id}/payment-proof', [AdminOrderController::class, 'viewPaymentProof']); // Ver comprobante de pago
        Route::delete('/{id}', [AdminOrderController::class, 'destroy']);                  // Eliminar orden
        Route::get('/user/{userId}', [AdminOrderController::class, 'getUserOrders']);      // Órdenes de un usuario
    });

    // Gestión de Deliveries
    Route::prefix('deliveries')->group(function () {
        Route::get('/', [DeliveryController::class, 'index']);                             // Listar todos los deliveries
        Route::post('/', [DeliveryController::class, 'store']);                            // Crear nuevo delivery
        Route::post('/assign/{orderId}', [DeliveryController::class, 'assignToOrder']);    // Asignar delivery a orden
        Route::delete('/unassign/{orderId}', [DeliveryController::class, 'unassignFromOrder']); // Desasignar delivery
        Route::post('/{userId}/assign-role', [DeliveryController::class, 'assignRole']); // Asignar rol delivery a usuario
        Route::get('/{deliveryId}/orders', [DeliveryController::class, 'getOrders']);      // Órdenes de un delivery
        Route::delete('/{id}', [DeliveryController::class, 'destroy']);                    // Eliminar delivery
    });

    // Gestión de Clientes
    Route::prefix('clients')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\Admin\UserController::class, 'index']);  // Listar todos los clientes
        Route::get('/{id}', [\App\Http\Controllers\API\Admin\UserController::class, 'show']); // Ver detalle de cliente
    });

    // Chat de Órdenes
    Route::prefix('chat')->group(function () {
        Route::get('/{orderId}/messages', [ChatController::class, 'getMessages']);         // Obtener mensajes de una orden
        Route::post('/{orderId}/messages', [ChatController::class, 'sendMessage']);        // Enviar mensaje
        Route::post('/{orderId}/read', [ChatController::class, 'markAsRead']);             // Marcar como leído
        Route::get('/{orderId}/unread', [ChatController::class, 'getUnreadCount']);        // Contar no leídos
    });

    // Sistema POS (Point of Sale)
    Route::prefix('pos')->group(function () {
        Route::get('/products', [PosController::class, 'getProducts']);                    // Listar productos para POS
        Route::get('/customers/search', [PosController::class, 'searchCustomer']);        // Buscar cliente por teléfono/cédula
        Route::get('/customers/{customerId}', [PosController::class, 'getCustomer']);       // Obtener info completa del cliente
        Route::post('/sales', [PosController::class, 'createSale']);                       // Crear venta POS
        Route::get('/sales', [PosController::class, 'getSalesHistory']);                  // Historial de ventas POS
    });

    // Configuración de Puntos
    Route::prefix('points-config')->group(function () {
        Route::get('/', [PointsConfigController::class, 'index']);                        // Obtener configuración
        Route::put('/', [PointsConfigController::class, 'update']);                        // Actualizar configuración
        Route::post('/calculate', [PointsConfigController::class, 'calculatePoints']);    // Calcular puntos
    });

    // Gestión de Puntos (Admin)
    Route::prefix('points')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\Admin\PointController::class, 'index']);           // Listar puntos de usuarios
        Route::get('/{userId}', [\App\Http\Controllers\API\Admin\PointController::class, 'show']);   // Ver puntos de un usuario
    });

    // Gestión de Almacenamiento
    Route::prefix('storage')->group(function () {
        Route::get('/verify', [\App\Http\Controllers\API\Admin\StorageController::class, 'verify']);  // Verificar configuración de almacenamiento
        Route::get('/payment-proofs', [\App\Http\Controllers\API\Admin\StorageController::class, 'listPaymentProofs']);  // Listar todos los comprobantes
        Route::get('/payment-proofs/{orderId}', [\App\Http\Controllers\API\Admin\StorageController::class, 'verifyPaymentProof']);  // Verificar comprobante específico
    });
});

// **********************************
// * Rutas de Prueba (Testing) - Solo para desarrollo *
// **********************************
// Ruta de prueba para enviar mensajes sin autenticación
// Ejemplo: GET /api/test/send-message/14?message=Hola desde test&user_id=1
Route::get('/test/send-message/{orderId}', [\App\Http\Controllers\API\TestChatController::class, 'sendTestMessage'])
    ->name('api.test.send-message');

// **********************************
// * Rutas de Delivery *
// **********************************
Route::group(['middleware' => ['auth:sanctum', 'role:delivery'], 'prefix' => 'delivery'], function () {
    Route::prefix('orders')->group(function () {
        Route::get('/', [DeliveryOrderController::class, 'index']);                        // Listar órdenes asignadas al delivery
        Route::put('/{orderId}/update-status', [DeliveryOrderController::class, 'updateStatus']);  // Actualizar estado (in_agency → on_the_way → delivered)
        Route::post('/{orderId}/update-location', [DeliveryOrderController::class, 'updateLocation']); // Actualizar ubicación en tiempo real
        Route::post('/{orderId}/sos', [DeliveryOrderController::class, 'sos']);          // Activar SOS para un pedido
    });
});
