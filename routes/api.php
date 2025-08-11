<?php

use App\Http\Controllers\API\AddressController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CartController;
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

    // Ruta para calcular costo de envío (puede ir fuera del prefix 'addresses' si es general)
    Route::post('/shipping-cost', [AddressController::class, 'calculateShippingCost']);

});
