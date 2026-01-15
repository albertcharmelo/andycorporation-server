<?php

use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\OrderController;
use Illuminate\Support\Facades\Route;

/**
 * Rutas de Administración (Panel Admin)
 * Protegidas por middleware 'auth' y 'role:admin,super_admin'
 */

Route::middleware(['auth', 'verified', 'role:admin,super_admin'])->prefix('admin')->name('admin.')->group(function () {

    // Gestión de Órdenes
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');           // Lista de órdenes
        Route::get('/{id}', [OrderController::class, 'show'])->name('show');         // Detalle de orden
        Route::put('/{id}/status', [OrderController::class, 'updateStatus'])->name('updateStatus'); // Actualizar estado
        Route::put('/{id}/notes', [OrderController::class, 'updateNotes'])->name('updateNotes');   // Actualizar notas
    });

    // Gestión de Deliveries
    Route::prefix('deliveries')->name('deliveries.')->group(function () {
        Route::get('/', [DeliveryController::class, 'index'])->name('index');        // Lista de deliveries
    });

    // Página de prueba de Pusher
    Route::get('/pusher-test', function () {
        return response()->json(['message' => 'Pusher test endpoint - use API endpoints for testing']);
    })->name('pusher-test');
});
