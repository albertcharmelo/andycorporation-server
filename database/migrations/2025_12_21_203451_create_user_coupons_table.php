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
        Schema::create('user_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('coupon_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['available', 'used', 'expired', 'disabled'])->default('available');
            $table->foreignId('used_in_order_id')->nullable()->constrained('orders')->onDelete('set null'); // Orden donde se usó
            $table->timestamp('used_at')->nullable(); // Fecha de uso
            $table->timestamps();

            // Índice único para evitar duplicados
            $table->unique(['user_id', 'coupon_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_coupons');
    }
};
