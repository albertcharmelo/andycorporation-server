<?php

use App\Http\Controllers\API\AddressController;
use App\Http\Controllers\API\Admin\ChatController;
use App\Http\Controllers\API\Admin\DeliveryController;
use App\Http\Controllers\API\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\CheckoutController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return [
        'user'    => $request->user(),
        'message' => 'Welcome to the API',
    ];
})->middleware('auth:sanctum');

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
        Route::get('/{deliveryId}/orders', [DeliveryController::class, 'getOrders']);      // Órdenes de un delivery
        Route::delete('/{id}', [DeliveryController::class, 'destroy']);                    // Eliminar delivery
    });

    // Chat de Órdenes
    Route::prefix('chat')->group(function () {
        Route::get('/{orderId}/messages', [ChatController::class, 'getMessages']);         // Obtener mensajes de una orden
        Route::post('/{orderId}/messages', [ChatController::class, 'sendMessage']);        // Enviar mensaje
        Route::post('/{orderId}/read', [ChatController::class, 'markAsRead']);             // Marcar como leído
        Route::get('/{orderId}/unread', [ChatController::class, 'getUnreadCount']);        // Contar no leídos
    });
});
