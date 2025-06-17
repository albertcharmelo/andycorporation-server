<?php

use App\Http\Controllers\API\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return [
        'user' => $request->user(),
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
    Route::get('/listproducts', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'getAllProducts']);
    Route::get('/searchByName', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'searchProducts']);
    Route::get('/popularProducts', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'getPopularProducts']);
    Route::get('/salesProducts', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'getSalesProducts']);
    Route::get('/{product}', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'getProduct']);
    ## Sincronizar con Wordpress
    Route::get('syncProducts', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'syncProducts']);
});
