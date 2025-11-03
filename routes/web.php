<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');
Route::get('syncProducts', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'syncProducts']);
Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Ruta de autenticación de broadcasting para Pusher (acepta sesión web)
Broadcast::routes(['middleware' => ['web', 'auth']]);

// Rutas de chat para admin con sesión web (se registran en web.php para tener prioridad y usar middleware web)
Route::middleware(['web', 'auth', 'role:admin,super_admin'])->prefix('api')->group(function () {
    Route::prefix('orders')->group(function () {
        Route::get('/{orderId}/chat', [\App\Http\Controllers\API\OrderChatController::class, 'getMessages']);
        Route::post('/{orderId}/chat', [\App\Http\Controllers\API\OrderChatController::class, 'sendMessage']);
        Route::put('/{orderId}/chat/mark-read', [\App\Http\Controllers\API\OrderChatController::class, 'markAsRead']);
        Route::get('/{orderId}/chat/stats', [\App\Http\Controllers\API\OrderChatController::class, 'getStats']);
        Route::get('/{orderId}/chat/attachment/{messageId}', [\App\Http\Controllers\API\OrderChatController::class, 'getAttachment']);
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
