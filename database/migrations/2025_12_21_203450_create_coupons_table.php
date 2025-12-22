<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Código del cupón
            $table->string('name'); // Nombre del cupón
            $table->text('description')->nullable(); // Descripción
            $table->enum('type', ['discount', 'free_delivery', 'gift', 'points_bonus']); // Tipo de cupón
            $table->decimal('discount_amount', 10, 2)->nullable(); // Monto de descuento (si aplica)
            $table->decimal('discount_percentage', 5, 2)->nullable(); // Porcentaje de descuento (si aplica)
            $table->integer('points_bonus')->nullable(); // Puntos bonus (si aplica)
            $table->decimal('min_purchase_amount', 10, 2)->nullable(); // Monto mínimo de compra
            $table->integer('max_uses')->nullable(); // Máximo de usos totales (null = ilimitado)
            $table->integer('max_uses_per_user')->default(1); // Máximo de usos por usuario
            $table->date('valid_from')->nullable(); // Fecha de inicio de validez
            $table->date('valid_until')->nullable(); // Fecha de fin de validez
            $table->boolean('is_active')->default(true); // Si está activo
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
