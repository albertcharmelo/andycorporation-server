<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');
Route::get('syncProducts', [\App\Http\Controllers\API\PRODUCTS\ProductsController::class, 'syncProducts']);
Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// DEBUG: Ruta temporal para verificar autenticación
Route::get('/debug-auth', function () {
    return response()->json([
        'authenticated' => auth()->check(),
        'user' => auth()->user() ? [
            'id' => auth()->user()->id,
            'name' => auth()->user()->name,
            'email' => auth()->user()->email,
        ] : null,
        'session_id' => session()->getId(),
        'csrf_token' => csrf_token(),
    ]);
})->middleware('web');

// DEBUG: Test de broadcasting auth
Route::post('/debug-broadcast-auth', function (\Illuminate\Http\Request $request) {
    \Log::info('Debug Broadcasting Auth', [
        'authenticated' => auth()->check(),
        'user_id' => auth()->id(),
        'headers' => $request->headers->all(),
        'cookies' => $request->cookies->keys(),
        'session_id' => session()->getId(),
        'channel_name' => $request->input('channel_name'),
    ]);
    
    return response()->json([
        'authenticated' => auth()->check(),
        'user_id' => auth()->id(),
        'session' => session()->all(),
    ]);
})->middleware(['web', 'auth']);

// Ruta personalizada de autenticación de broadcasting para Pusher
// Usa nuestro controlador personalizado que valida el usuario
Route::post('/broadcasting/auths', [\App\Http\Controllers\BroadcastingAuthController::class, 'authenticate'])
    ->middleware(['web', 'auth'])
    ->name('broadcasting.auths');

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
